<?php
// dashboard_admin.php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

// Authentication check - refactored to eliminate redirect loops
// Check 1: Is the user logged in at all?
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    // User is not logged in - redirect to login, but prevent redirect loops
    error_log('Admin Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
// Make sure role comparison is case-insensitive
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    // User has wrong role - log and redirect
    error_log('Access denied: User ' . $_SESSION['employee_id'] . ' with role ' . 
              ($_SESSION['role'] ?? 'none') . ' attempted to access admin dashboard');
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}

// DB
require_once $root_path . '/config/db.php'; // adjust relative path if needed
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
if ($stmt) {
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
}

// Dashboard Statistics
try {
    // Total Patients
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM patients');
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $defaults['stats']['total_patients'] = $row['count'] ?? 0;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Today's Appointments
    $today = date('Y-m-d');
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM appointments WHERE DATE(date) = ?');
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $defaults['stats']['today_appointments'] = $row['count'] ?? 0;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Pending Lab Results
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE status = "pending"');
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $defaults['stats']['pending_lab_results'] = $row['count'] ?? 0;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Total Employees
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM employees');
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $defaults['stats']['total_employees'] = $row['count'] ?? 0;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Monthly Revenue (current month)
    $current_month = date('Y-m');
    $stmt = $conn->prepare('SELECT SUM(amount) as total FROM billing WHERE DATE_FORMAT(date, "%Y-%m") = ? AND status = "paid"');
    if ($stmt) {
        $stmt->bind_param("s", $current_month);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $defaults['stats']['monthly_revenue'] = $row['total'] ?? 0;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

try {
    // Queue Count
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM patient_queue WHERE status = "waiting"');
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $defaults['stats']['queue_count'] = $row['count'] ?? 0;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    // table might not exist yet; ignore
}

// Recent Activities (latest 5)
try {
    $stmt = $conn->prepare('SELECT activity, created_at FROM admin_activity_log WHERE employee_id = ? ORDER BY created_at DESC LIMIT 5');
    if ($stmt) {
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
    }
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
    if ($stmt) {
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
    }
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
    if ($stmt) {
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
    }
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
        .content-wrapper {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
            }
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .dashboard-title {
            font-size: 1.8rem;
            color: #0077b6;
            margin: 0;
        }
        
        .dashboard-actions {
            display: flex;
            gap: 1rem;
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border-left: 4px solid #0077b6;
        }
        
        .info-card h2 {
            font-size: 1.4rem;
            color: #333;
            margin-top: 0;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-card h2 i {
            color: #0077b6;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .card-title {
            font-size: 1.2rem;
            color: #0077b6;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-status {
            padding: 0.3rem 0.6rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-confirmed {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-active {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .status-completed {
            background-color: #e5e7eb;
            color: #374151;
        }
        
        .card-content {
            margin-bottom: 1rem;
        }
        
        .card-detail {
            display: flex;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6b7280;
            width: 70px;
            flex-shrink: 0;
        }
        
        .detail-value {
            color: #1f2937;
        }
        
        .card-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: #0077b6;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #023e8a;
        }
        
        .btn-secondary {
            background-color: #f3f4f6;
            color: #1f2937;
        }
        
        .btn-secondary:hover {
            background-color: #e5e7eb;
        }
        
        .section-divider {
            margin: 2.5rem 0;
            border: none;
            border-top: 1px solid #e5e7eb;
        }
        
        .quick-actions {
            margin-top: 2rem;
        }
        
        .actions-title {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 1.5rem;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 160px;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: #333;
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #0077b6;
            transition: transform 0.2s;
        }
        
        .action-card:hover .action-icon {
            transform: scale(1.1);
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        
        .action-description {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-top: 80px;
            }
            
            .dashboard-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .action-card {
                min-height: 140px;
            }
        }
        
        /* Welcome message animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .welcome-message {
            animation: fadeInUp 0.8s ease-out;
        }
        
        /* Card entry animation */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .animated-card {
            animation: slideInRight 0.5s ease-out forwards;
            opacity: 0;
        }
        
        .animated-card:nth-child(1) {
            animation-delay: 0.2s;
        }
        
        .animated-card:nth-child(2) {
            animation-delay: 0.4s;
        }
        
        .animated-card:nth-child(3) {
            animation-delay: 0.6s;
        }
        
        .animated-card:nth-child(4) {
            animation-delay: 0.8s;
        }
        
        /* Accessibility improvements */
        .visually-hidden {
            border: 0;
            clip: rect(0 0 0 0);
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            width: 1px;
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
        }
        
        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            white-space: nowrap;
            z-index: 10;
        }

        /* Statistics Grid for Admin - keeping the 6-card layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card.patients { border-left-color: #667eea; }
        .stat-card.appointments { border-left-color: #f093fb; }
        .stat-card.lab { border-left-color: #4facfe; }
        .stat-card.employees { border-left-color: #43e97b; }
        .stat-card.revenue { border-left-color: #fa709a; }
        .stat-card.queue { border-left-color: #a8edea; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2rem;
            color: #0077b6;
            opacity: 0.8;
        }

        .stat-card.patients .stat-icon { color: #667eea; }
        .stat-card.appointments .stat-icon { color: #f093fb; }
        .stat-card.lab .stat-icon { color: #4facfe; }
        .stat-card.employees .stat-icon { color: #43e97b; }
        .stat-card.revenue .stat-icon { color: #fa709a; }
        .stat-card.queue .stat-icon { color: #a8edea; }

        /* Queue Overview Section */
        .queue-overview {
            margin-bottom: 30px;
        }

        .section-header-with-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header-with-action h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--cho-primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .queue-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .refresh-btn, .monitor-btn, .display-btn {
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--cho-border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--cho-transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .refresh-btn:hover, .monitor-btn:hover, .display-btn:hover {
            background: linear-gradient(135deg, var(--cho-primary-dark) 0%, #001d3d 100%);
            transform: translateY(-2px);
            box-shadow: var(--cho-shadow-lg);
            color: white;
            text-decoration: none;
        }

        .queue-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .queue-station-card {
            background: white;
            border: 2px solid var(--cho-border);
            border-radius: var(--cho-border-radius-lg);
            padding: 20px;
            box-shadow: var(--cho-shadow);
            transition: var(--cho-transition);
            position: relative;
        }

        .queue-station-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--cho-shadow-lg);
            border-color: var(--cho-primary);
        }

        .queue-station-card.active {
            border-color: var(--cho-success);
            background: linear-gradient(135deg, #f8fff9 0%, white 100%);
        }

        .queue-station-card.inactive {
            border-color: var(--cho-danger);
            background: #fdf2f2;
            opacity: 0.8;
        }

        .queue-station-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .queue-station-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--cho-primary-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .queue-station-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .queue-station-status.active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--cho-success);
        }

        .queue-station-status.inactive {
            background: rgba(220, 53, 69, 0.1);
            color: var(--cho-danger);
        }

        .queue-stats-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 15px 0;
        }

        .queue-stat-item {
            text-align: center;
            padding: 10px;
            background: var(--cho-light);
            border-radius: var(--cho-border-radius);
        }

        .queue-stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--cho-primary);
            display: block;
        }

        .queue-stat-label {
            font-size: 12px;
            color: var(--cho-secondary);
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 5px;
        }

        .queue-current-patient {
            background: linear-gradient(135deg, var(--cho-info) 0%, #0dcaf0 100%);
            color: white;
            padding: 12px;
            border-radius: var(--cho-border-radius);
            margin: 10px 0;
            text-align: center;
        }

        .queue-current-patient .patient-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .queue-current-patient .patient-details {
            font-size: 14px;
            opacity: 0.9;
        }

        .loading-state, .error-state {
            text-align: center;
            padding: 40px;
            color: var(--cho-secondary);
            grid-column: 1 / -1;
        }

        .error-state {
            color: var(--cho-danger);
        }

        .loading-state i, .error-state i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Info Layout for Admin-specific sections */
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

        /* Card Sections for admin specific content */
        .card-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
            margin-bottom: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-header h3 i {
            color: #0077b6;
        }

        .view-more-btn {
            color: #0077b6;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .view-more-btn:hover {
            color: #023e8a;
            text-decoration: none;
        }

        /* Tables */
        .table-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border-radius: 5px;
            border: 1px solid #e5e7eb;
        }

        .notification-table {
            width: 100%;
            border-collapse: collapse;
        }

        .notification-table th,
        .notification-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .notification-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-table td {
            color: #6b7280;
        }

        /* Activity Log */
        .activity-log {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-log li {
            padding: 0.75rem;
            border-left: 3px solid #0077b6;
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            border-radius: 0 5px 5px 0;
            font-size: 0.9rem;
            color: #6b7280;
        }

        /* Status Badges */
        .alert-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .alert-danger {
            background-color: #fecaca;
            color: #991b1b;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .priority-high {
            color: #dc2626;
            font-weight: 600;
        }

        .priority-normal {
            color: #16a34a;
        }

        .priority-low {
            color: #6b7280;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
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
            border-bottom: 1px solid #e5e7eb;
        }

        .status-item:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>

    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include '../../../includes/sidebar_admin.php';
    ?>

    <main class="content-wrapper">
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Welcome back, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>Admin Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="patient_records_management.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage Patients
                </a>
                <a href="appointments_management.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
            </div>
        </section>
        
        <section class="info-card">
            <h2><i class="fas fa-chart-line"></i> System Overview</h2>
            <div class="notification-list">
                <p>Here's a quick overview of your system status and performance metrics.</p>
            </div>
        </section>
        <section class="stats-grid">
            <div class="stat-card patients animated-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_patients']); ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card appointments animated-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['today_appointments']); ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            <div class="stat-card lab animated-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-vials"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_lab_results']); ?></div>
                <div class="stat-label">Pending Lab Results</div>
            </div>
            <div class="stat-card employees animated-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_employees']); ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card revenue animated-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                </div>
                <div class="stat-number">₱<?php echo number_format($defaults['stats']['monthly_revenue'], 2); ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
            <div class="stat-card queue animated-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['queue_count']); ?></div>
                <div class="stat-label">Patients in Queue</div>
            </div>
        </section>

        <hr class="section-divider">
        
        <section class="quick-actions">
            <h2 class="actions-title">Quick Actions</h2>
            
            <div class="actions-grid">
                <a href="patient_records_management.php" class="action-card">
                    <i class="fas fa-users action-icon"></i>
                    <h3 class="action-title">Manage Patients</h3>
                    <p class="action-description">Add, edit, or view patient records and information</p>
                </a>
                
                <a href="appointments_management.php" class="action-card">
                    <i class="fas fa-calendar-check action-icon"></i>
                    <h3 class="action-title">Schedule Appointments</h3>
                    <p class="action-description">Manage patient appointments and doctor schedules</p>
                </a>
                
                <a href="employee_management.php" class="action-card">
                    <i class="fas fa-user-tie action-icon"></i>
                    <h3 class="action-title">Manage Staff</h3>
                    <p class="action-description">Add, edit, or manage employee accounts and roles</p>
                </a>
                
                <a href="../reports/reports.php" class="action-card">
                    <i class="fas fa-chart-bar action-icon"></i>
                    <h3 class="action-title">Generate Reports</h3>
                    <p class="action-description">View analytics and generate comprehensive reports</p>
                </a>
                
                <a href="../queueing/queue_management.php" class="action-card">
                    <i class="fas fa-list-ol action-icon"></i>
                    <h3 class="action-title">Manage Queue</h3>
                    <p class="action-description">Control patient flow and queue management system</p>
                </a>
                
                <a href="../billing/billing_management.php" class="action-card">
                    <i class="fas fa-file-invoice-dollar action-icon"></i>
                    <h3 class="action-title">Billing Management</h3>
                    <p class="action-description">Process payments and manage billing operations</p>
                </a>
            </div>
        </section>

        <!-- Queue Overview Section -->
        <section class="queue-overview">
            <div class="section-header-with-action">
                <h2><i class="fas fa-tv"></i> Queue Overview</h2>
                <div class="queue-actions">
                    <button onclick="refreshQueueOverview()" class="refresh-btn" title="Refresh Queue Data">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <a href="../../queueing/admin_monitor.php" class="monitor-btn" title="Open Queue Monitor">
                        <i class="fas fa-external-link-alt"></i> Full Monitor
                    </a>
                    <a href="../../queueing/public_display_selector.php" class="display-btn" title="Open Display Selector">
                        <i class="fas fa-tv"></i> Public Displays
                    </a>
                </div>
            </div>
            
            <div class="queue-cards-grid" id="queueOverview">
                <!-- Queue overview content will be loaded here -->
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading queue data...</p>
                </div>
            </div>
        </section>

        <hr class="section-divider">

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
                        <a href="admin_tasks.php" class="view-more-btn">
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
    </main>
    
    <!-- Universal Framework Integration -->
    <script src="../../../assets/js/station-manager.js"></script>
    <script src="../../../assets/js/queue-sync.js"></script>

    <script>
        class AdminDashboardManager {
            constructor() {
                this.refreshInterval = null;
                this.refreshRate = 8000; // 8 seconds for dashboard
                this.isRefreshing = false;
                this.errorCount = 0;
                this.maxErrors = 3;
                
                this.initializeDashboard();
                this.loadQueueOverview();
                this.startAutoRefresh();
                this.setupEventListeners();
            }
            
            initializeDashboard() {
                console.log('📊 Admin Dashboard Manager initialized');
                
                // Simple animation for the cards
                const cards = document.querySelectorAll('.animated-card');
                cards.forEach(card => {
                    card.style.opacity = '1';
                });
                
                // Request notification permission
                this.requestNotificationPermission();
            }
            
            async requestNotificationPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    await Notification.requestPermission();
                }
            }
            
            setupEventListeners() {
                // Listen for queue updates from other windows
                window.addEventListener('message', (event) => {
                    if (event.data.type === 'queue_updated') {
                        console.log('📡 Received queue update notification - refreshing dashboard');
                        this.loadQueueOverview();
                    }
                });
                
                // Handle visibility changes
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.pauseRefresh();
                    } else {
                        this.resumeRefresh();
                    }
                });
            }
            
            startAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                
                this.refreshInterval = setInterval(() => {
                    if (!document.hidden && !this.isRefreshing) {
                        this.loadQueueOverview();
                    }
                }, this.refreshRate);
                
                console.log(`⏱️ Dashboard auto-refresh started (${this.refreshRate/1000}s intervals)`);
            }
            
            pauseRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                    console.log('⏸️ Dashboard auto-refresh paused');
                }
            }
            
            resumeRefresh() {
                if (!this.refreshInterval) {
                    this.startAutoRefresh();
                    this.loadQueueOverview(); // Immediate refresh when tab becomes visible
                    console.log('▶️ Dashboard auto-refresh resumed');
                }
            }
            
            async loadQueueOverview() {
                if (this.isRefreshing) return;
                
                this.isRefreshing = true;
                const queueOverview = document.getElementById('queueOverview');
                
                try {
                    console.log('🔄 Loading queue overview...');
                    
                    // Fetch queue overview data
                    const response = await fetch('dashboard_queue_api.php', {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.renderQueueOverview(data.stations);
                        this.updateQueueStatistic(data.overall_stats);
                        console.log('✅ Queue overview loaded successfully');
                        this.errorCount = 0; // Reset error count on success
                    } else {
                        throw new Error(data.message || 'Failed to load queue data');
                    }
                    
                    // Broadcast update to other windows
                    this.broadcastUpdate();
                    
                } catch (error) {
                    console.error('❌ Error loading queue overview:', error);
                    this.errorCount++;
                    
                    if (this.errorCount >= this.maxErrors) {
                        this.showErrorState('Too many errors occurred. Please refresh the page.');
                    } else {
                        this.showErrorState(`Error loading queue data: ${error.message}`);
                    }
                } finally {
                    this.isRefreshing = false;
                }
            }
            
            renderQueueOverview(stations) {
                const queueOverview = document.getElementById('queueOverview');
                
                if (!stations || stations.length === 0) {
                    queueOverview.innerHTML = `
                        <div class="error-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No active stations found</p>
                        </div>
                    `;
                    return;
                }
                
                const stationsHtml = stations.map(station => {
                    const isActive = station.is_active && station.assigned_employee;
                    const hasCurrentPatient = station.current_patient;
                    
                    return `
                        <div class="queue-station-card ${isActive ? 'active' : 'inactive'}">
                            <div class="queue-station-header">
                                <h4 class="queue-station-title">
                                    <i class="${this.getStationIcon(station.station_type)}"></i>
                                    ${station.station_name}
                                </h4>
                                <div class="queue-station-status ${isActive ? 'active' : 'inactive'}">
                                    <i class="fas fa-circle"></i>
                                    ${isActive ? 'Active' : 'Inactive'}
                                </div>
                            </div>
                            
                            ${isActive ? `
                                <div class="queue-stats-row">
                                    <div class="queue-stat-item">
                                        <span class="queue-stat-number">${station.queue_stats?.waiting_count || 0}</span>
                                        <div class="queue-stat-label">Waiting</div>
                                    </div>
                                    <div class="queue-stat-item">
                                        <span class="queue-stat-number">${station.queue_stats?.in_progress_count || 0}</span>
                                        <div class="queue-stat-label">In Progress</div>
                                    </div>
                                </div>
                                
                                ${hasCurrentPatient ? `
                                    <div class="queue-current-patient">
                                        <div class="patient-name">
                                            <i class="fas fa-user"></i> ${station.current_patient.patient_name}
                                        </div>
                                        <div class="patient-details">
                                            ${station.current_patient.formatted_code || `#${station.current_patient.queue_number}`} • 
                                            Started: ${this.formatTime(station.current_patient.time_started)}
                                        </div>
                                    </div>
                                ` : `
                                    <div style="text-align: center; padding: 15px; color: var(--cho-secondary);">
                                        <i class="fas fa-check-circle"></i> No current patient
                                    </div>
                                `}
                                
                                <div style="text-align: center; margin-top: 10px;">
                                    <small style="color: var(--cho-success); font-weight: 600;">
                                        <i class="fas fa-user"></i> ${station.assigned_employee}
                                    </small>
                                </div>
                            ` : `
                                <div style="text-align: center; padding: 20px; color: var(--cho-danger);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <p>Station inactive or no staff assigned</p>
                                </div>
                            `}
                        </div>
                    `;
                }).join('');
                
                queueOverview.innerHTML = stationsHtml;
            }
            
            getStationIcon(stationType) {
                const iconMap = {
                    'triage': 'fas fa-user-md',
                    'consultation': 'fas fa-stethoscope',
                    'lab': 'fas fa-microscope',
                    'pharmacy': 'fas fa-pills',
                    'billing': 'fas fa-file-invoice-dollar',
                    'document': 'fas fa-file-alt'
                };
                return iconMap[stationType] || 'fas fa-hospital';
            }
            
            formatTime(timeString) {
                const time = new Date(timeString);
                return time.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            }
            
            updateQueueStatistic(overallStats) {
                // Update the main queue statistic card if data is available
                const queueStatCard = document.querySelector('.stat-card.queue .stat-number');
                if (queueStatCard && overallStats) {
                    const totalWaiting = overallStats.total_waiting || 0;
                    queueStatCard.textContent = new Intl.NumberFormat().format(totalWaiting);
                }
            }
            
            showErrorState(message) {
                const queueOverview = document.getElementById('queueOverview');
                queueOverview.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>${message}</p>
                        <button onclick="dashboardManager.loadQueueOverview()" class="refresh-btn" style="margin-top: 10px;">
                            <i class="fas fa-retry"></i> Retry
                        </button>
                    </div>
                `;
            }
            
            broadcastUpdate() {
                // Notify other windows about the update
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'dashboard_updated',
                        timestamp: Date.now()
                    }, '*');
                }
            }
            
            // Manual refresh method
            manualRefresh() {
                console.log('🔄 Manual refresh triggered');
                this.loadQueueOverview();
            }
        }
        
        // JavaScript implementation of queue code formatter
        function formatQueueCodeForDisplay(queueCode) {
            if (!queueCode) return '';
            const parts = queueCode.split('-');
            if (parts.length >= 3) {
                const timeSlot = parts[1];
                const sequence = parts[2];
                if (timeSlot.length === 3) {
                    const hours = timeSlot.substring(0, 2);
                    const slotLetter = timeSlot.substring(2);
                    const minuteMap = { 'A': '00', 'B': '15', 'C': '30', 'D': '45' };
                    const minutes = minuteMap[slotLetter] || '00';
                    return `${hours}${minutes.charAt(0)}-${sequence}`;
                }
                return `${timeSlot}-${sequence}`;
            }
            return queueCode;
        }
        
        // Initialize dashboard manager
        let dashboardManager;
        
        document.addEventListener('DOMContentLoaded', function() {
            dashboardManager = new AdminDashboardManager();
        });
        
        // Global function for manual refresh button
        function refreshQueueOverview() {
            if (dashboardManager) {
                dashboardManager.manualRefresh();
                
                // Add visual feedback
                const refreshBtn = document.querySelector('.refresh-btn i');
                if (refreshBtn) {
                    refreshBtn.style.transform = 'rotate(360deg)';
                    refreshBtn.style.transition = 'transform 0.5s ease';
                    setTimeout(() => {
                        refreshBtn.style.transform = 'rotate(0deg)';
                    }, 500);
                }
            }
        }
    </script>
</body>

</html>
