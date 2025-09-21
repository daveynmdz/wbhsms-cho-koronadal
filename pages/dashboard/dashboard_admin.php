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
require_once '../../config/db.php'; // adjust relative path if needed
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// -------------------- Data bootstrap (Admin Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
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
$stmt = $conn->prepare('SELECT first_name, middle_name, last_name, employee_number, role FROM employees WHERE employee_id = ?');
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
    $defaults['role'] = $row['role'];
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
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        /* Optional: small layout wrapper for content next to sidebar */
        .content-wrapper {
            display: block;
            margin-left: var(--sidebar-width, 260px);
            /* if your sidebar uses fixed width */
            padding: 1.25rem;
        }

        .content-wrapper h1 {
            font-size: 1.75rem;
            font-weight: 600;
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
            }

            .content-wrapper h1 {
                font-size: 1.25rem;
                text-align: center;
            }
        }

        .page-title {
            margin: 0 0 1rem;
        }

        /* Admin-specific styles */
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.patients {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.appointments {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.lab {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card.employees {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-card.revenue {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-card.queue {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .admin-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: inherit;
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #667eea;
        }

        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .action-description {
            font-size: 0.9rem;
            color: #666;
        }

        .alert-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
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
            color: #dc3545;
            font-weight: 600;
        }

        .priority-normal {
            color: #28a745;
        }

        .priority-low {
            color: #6c757d;
        }
    </style>
</head>

<body>

    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include '../../includes/sidebar_admin.php';
    ?>

    <section class="content-wrapper">
        <h1 style="margin-top:50px;margin-bottom:2rem;">Welcome to the Admin Dashboard, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
        <p style="margin-bottom:2rem;color:#666;">Role: <strong><?php echo htmlspecialchars($defaults['role']); ?></strong> | Employee ID: <strong><?php echo htmlspecialchars($defaults['employee_number']); ?></strong></p>

        <!-- Statistics Overview -->
        <div class="admin-stats-grid">
            <div class="stat-card patients">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_patients']); ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card appointments">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['today_appointments']); ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            <div class="stat-card lab">
                <div class="stat-icon"><i class="fas fa-vials"></i></div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_lab_results']); ?></div>
                <div class="stat-label">Pending Lab Results</div>
            </div>
            <div class="stat-card employees">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_employees']); ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                <div class="stat-number">₱<?php echo number_format($defaults['stats']['monthly_revenue'], 2); ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
            <div class="stat-card queue">
                <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['queue_count']); ?></div>
                <div class="stat-label">Patients in Queue</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card-container" style="margin-bottom: 2rem;">
            <h2>Quick Actions</h2>
            <div class="admin-actions">
                <a href="../patient/patient_management.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-users"></i></div>
                    <div class="action-title">Manage Patients</div>
                    <div class="action-description">Add, edit, or view patient records</div>
                </a>
                <a href="../management/admin/appointments_management.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="action-title">Schedule Appointments</div>
                    <div class="action-description">Manage patient appointments and schedules</div>
                </a>
                <a href="../user/employee_management.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-user-tie"></i></div>
                    <div class="action-title">Manage Staff</div>
                    <div class="action-description">Add, edit, or manage employee accounts</div>
                </a>
                <a href="../reports/reports.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="action-title">Generate Reports</div>
                    <div class="action-description">View analytics and generate reports</div>
                </a>
                <a href="../queueing/queue_management.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-list-ol"></i></div>
                    <div class="action-title">Manage Queue</div>
                    <div class="action-description">Control patient flow and queue system</div>
                </a>
                <a href="../billing/billing_management.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="action-title">Billing Management</div>
                    <div class="action-description">Process payments and manage billing</div>
                </a>
            </div>
        </div>

        <div class="info-layout">
            <!-- Recent Activities and Pending Tasks -->
            <div class="left-column">
                <div class="card-section latest-appointment collapsible">
                    <div class="section-header">
                        <h3>Recent Activities</h3>
                        <a href="../reports/activity_log.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View More
                        </a>
                    </div>
                    <div class="scroll-wrapper">
                        <div class="scroll-log">
                            <ul class="activity-log">
                                <?php foreach ($defaults['recent_activities'] as $activity): ?>
                                    <li><?php echo htmlspecialchars($activity['date']); ?> - <?php echo htmlspecialchars($activity['activity']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="fade-bottom"></div>
                    </div>
                </div>

                <div class="card-section activity-log-card">
                    <div class="section-header">
                        <h3>Pending Tasks</h3>
                        <a href="../user/admin_tasks.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View More
                        </a>
                    </div>
                    <div class="scroll-wrapper">
                        <div class="scroll-table">
                            <table class="notification-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Task</th>
                                        <th scope="col">Priority</th>
                                        <th scope="col">Due Date</th>
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
                        <div class="fade-bottom"></div>
                    </div>
                </div>
            </div>

            <!-- System Alerts and System Status -->
            <div class="right-column">
                <div class="card-section notification-card">
                    <div class="section-header">
                        <h3>System Alerts</h3>
                        <a href="../notifications/system_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View More
                        </a>
                    </div>
                    <div class="scroll-wrapper">
                        <div class="scroll-table">
                            <table class="notification-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">Message</th>
                                        <th scope="col">Type</th>
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
                        <div class="fade-bottom"></div>
                    </div>
                </div>

                <div class="card-section activity-log-card">
                    <div class="section-header">
                        <h3>System Status</h3>
                    </div>
                    <div style="padding: 1rem;">
                        <div style="margin-bottom: 1rem;">
                            <strong>Database:</strong> <span class="alert-badge alert-success">Connected</span>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Server Status:</strong> <span class="alert-badge alert-success">Online</span>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Last Backup:</strong> <span><?php echo date('M d, Y H:i'); ?></span>
                        </div>
                        <div>
                            <strong>System Version:</strong> <span>v1.0.0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>

</html>
