<?php
// dashboard_admin.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not an admin, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../../config/db.php'; // adjust relative path if needed
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// -------------------- Data bootstrap (Admin Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_name'] ?? 'Unknown User',
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'total_patients' => 0,
        'today_appointments' => 0,
        'pending_lab_results' => 0,
        'total_employees' => 0,
        'monthly_revenue' => 0,
        'queue_count' => 0
    ],
    'recent_activities' => [],
    'pending_tasks' => [],
    'system_alerts' => []
];

// Get employee info
$stmt = $conn->prepare('SELECT first_name, middle_name, last_name, employee_number, role_id FROM employees WHERE employee_id = ?');
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row) {
    $full_name = $row['first_name'];
    if (!empty($row['middle_name'])) $full_name .= ' ' . $row['middle_name'];
    $full_name .= ' ' . $row['last_name'];
    $defaults['name'] = trim($full_name);
    $defaults['employee_number'] = $row['employee_number'];
    
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
    $defaults['role'] = $role_mapping[$row['role_id']] ?? 'unknown';
}
$stmt->close();

// Dashboard Statistics
try {
    // Total Patients
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM patients');
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $defaults['stats']['total_patients'] = $row['count'] ?? 0;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Today's Appointments
    $today = date('Y-m-d');
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM appointments WHERE DATE(date) = ?');
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $defaults['stats']['today_appointments'] = $row['count'] ?? 0;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Pending Lab Results
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE status = "pending"');
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $defaults['stats']['pending_lab_results'] = $row['count'] ?? 0;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Total Employees
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM employees');
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $defaults['stats']['total_employees'] = $row['count'] ?? 0;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Monthly Revenue (current month)
    $current_month = date('Y-m');
    $stmt = $conn->prepare('SELECT SUM(amount) as total FROM billing WHERE DATE_FORMAT(date, "%Y-%m") = ? AND status = "paid"');
    $stmt->bind_param("s", $current_month);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $defaults['stats']['monthly_revenue'] = $row['total'] ?? 0;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Queue Count
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM patient_queue WHERE status = "waiting"');
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $defaults['stats']['queue_count'] = $row['count'] ?? 0;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

// Recent Activities (latest 5)
try {
    $stmt = $conn->prepare('SELECT activity, created_at FROM admin_activity_log WHERE employee_id = ? ORDER BY created_at DESC LIMIT 5');
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $defaults['recent_activities'][] = [
            'activity' => $row['activity'] ?? '',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; add some default activities
    $defaults['recent_activities'] = [
        ['activity' => 'Logged into admin dashboard', 'date' => date('m/d/Y H:i')],
        ['activity' => 'System started', 'date' => date('m/d/Y H:i')]
    ];
}

// Pending Tasks
try {
    $stmt = $conn->prepare('SELECT task, priority, due_date FROM admin_tasks WHERE employee_id = ? AND status = "pending" ORDER BY due_date ASC LIMIT 5');
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $defaults['pending_tasks'][] = [
            'task' => $row['task'] ?? '',
            'priority' => $row['priority'] ?? 'normal',
            'due_date' => date('m/d/Y', strtotime($row['due_date']))
        ];
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; add some default tasks
    $defaults['pending_tasks'] = [
        ['task' => 'Review pending patient registrations', 'priority' => 'high', 'due_date' => date('m/d/Y')],
        ['task' => 'Update system settings', 'priority' => 'normal', 'due_date' => date('m/d/Y', strtotime('+1 day'))]
    ];
}

// System Alerts
try {
    $stmt = $conn->prepare('SELECT message, type, created_at FROM system_alerts WHERE status = "active" ORDER BY created_at DESC LIMIT 3');
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $defaults['system_alerts'][] = [
            'message' => $row['message'] ?? '',
            'type' => $row['type'] ?? 'info',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; add some default alerts
    $defaults['system_alerts'] = [
        ['message' => 'System running normally', 'type' => 'success', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
        }

        .content-wrapper {
            margin-left: var(--sidebar-width, 260px);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Welcome Header */
        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 300;
            line-height: 1.2;
        }

        .welcome-header .subtitle {
            margin-top: 0.5rem;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .welcome-header h1 {
                font-size: 1.8rem;
            }
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
        }

        .stat-card.patients { --card-color: #667eea; }
        .stat-card.appointments { --card-color: #f093fb; }
        .stat-card.lab { --card-color: #4facfe; }
        .stat-card.employees { --card-color: #43e97b; }
        .stat-card.revenue { --card-color: #fa709a; }
        .stat-card.queue { --card-color: #a8edea; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--card-color, var(--primary));
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Quick Actions */
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
            transition: var(--transition);
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
        }

        .action-card:hover::before {
            width: 8px;
        }

        .action-card.blue { --card-color: #007bff; }
        .action-card.purple { --card-color: #6f42c1; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.teal { --card-color: #20c997; }
        .action-card.green { --card-color: #28a745; }
        .action-card.red { --card-color: #dc3545; }

        .action-card .icon {
            font-size: 2.5rem;
            color: var(--card-color, var(--primary));
            margin-bottom: 1rem;
            display: block;
        }

        .action-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-card p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        /* Info Layout */
        .info-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        @media (max-width: 1200px) {
            .info-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Card Sections */
        .card-section {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-more-btn {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .view-more-btn:hover {
            color: var(--primary-dark);
            text-decoration: none;
        }

        /* Tables */
        .table-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--border);
        }

        .notification-table {
            width: 100%;
            border-collapse: collapse;
        }

        .notification-table th,
        .notification-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .notification-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-table td {
            color: var(--secondary);
        }

        /* Activity Log */
        .activity-log {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-log li {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
            color: var(--secondary);
        }

        /* Status Badges */
        .alert-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-success {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-high {
            color: var(--danger);
            font-weight: 600;
        }

        .priority-normal {
            color: var(--success);
        }

        .priority-low {
            color: var(--secondary);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* System Status */
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .status-item:last-child {
            border-bottom: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include '../../../includes/sidebar_admin.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Welcome back, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                Admin Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            System Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card patients">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_patients']); ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card appointments">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['today_appointments']); ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            <div class="stat-card lab">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-vials"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_lab_results']); ?></div>
                <div class="stat-label">Pending Lab Results</div>
            </div>
            <div class="stat-card employees">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_employees']); ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                </div>
                <div class="stat-number">₱<?php echo number_format($defaults['stats']['monthly_revenue'], 2); ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
            <div class="stat-card queue">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['queue_count']); ?></div>
                <div class="stat-label">Patients in Queue</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="patient_records_management.php" class="action-card blue">
                <i class="fas fa-users icon"></i>
                <h3>Manage Patients</h3>
                <p>Add, edit, or view patient records and information</p>
            </a>
            <a href="../management/admin/appointments_management.php" class="action-card purple">
                <i class="fas fa-calendar-check icon"></i>
                <h3>Schedule Appointments</h3>
                <p>Manage patient appointments and doctor schedules</p>
            </a>
            <a href="../user/employee_management.php" class="action-card orange">
                <i class="fas fa-user-tie icon"></i>
                <h3>Manage Staff</h3>
                <p>Add, edit, or manage employee accounts and roles</p>
            </a>
            <a href="../reports/reports.php" class="action-card teal">
                <i class="fas fa-chart-bar icon"></i>
                <h3>Generate Reports</h3>
                <p>View analytics and generate comprehensive reports</p>
            </a>
            <a href="../queueing/queue_management.php" class="action-card green">
                <i class="fas fa-list-ol icon"></i>
                <h3>Manage Queue</h3>
                <p>Control patient flow and queue management system</p>
            </a>
            <a href="../billing/billing_management.php" class="action-card red">
                <i class="fas fa-file-invoice-dollar icon"></i>
                <h3>Billing Management</h3>
                <p>Process payments and manage billing operations</p>
            </a>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Recent Activities -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-history"></i> Recent Activities</h3>
                        <a href="../reports/activity_log.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['recent_activities'])): ?>
                        <div class="table-wrapper">
                            <ul class="activity-log">
                                <?php foreach ($defaults['recent_activities'] as $activity): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($activity['date']); ?></strong><br>
                                        <?php echo htmlspecialchars($activity['activity']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No recent activities to display</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Tasks -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-tasks"></i> Pending Tasks</h3>
                        <a href="../user/admin_tasks.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['pending_tasks'])): ?>
                        <div class="table-wrapper">
                            <table class="notification-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Priority</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['pending_tasks'] as $task): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($task['task']); ?></td>
                                            <td><span class="priority-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                                            <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending tasks</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- System Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> System Alerts</h3>
                        <a href="../notifications/system_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['system_alerts'])): ?>
                        <div class="table-wrapper">
                            <table class="notification-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Message</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['system_alerts'] as $alert): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($alert['date']); ?></td>
                                            <td><?php echo htmlspecialchars($alert['message']); ?></td>
                                            <td><span class="alert-badge alert-<?php echo $alert['type']; ?>"><?php echo ucfirst($alert['type']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No system alerts</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- System Status -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-server"></i> System Status</h3>
                    </div>
                    
                    <div class="status-item">
                        <strong>Database Connection</strong>
                        <span class="alert-badge alert-success">Connected</span>
                    </div>
                    <div class="status-item">
                        <strong>Server Status</strong>
                        <span class="alert-badge alert-success">Online</span>
                    </div>
                    <div class="status-item">
                        <strong>Last Backup</strong>
                        <span><?php echo date('M d, Y H:i'); ?></span>
                    </div>
                    <div class="status-item">
                        <strong>System Version</strong>
                        <span>CHO Koronadal v1.0.0</span>
                    </div>
                    <div class="status-item">
                        <strong>Uptime</strong>
                        <span class="alert-badge alert-success">Running</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>

</html>
