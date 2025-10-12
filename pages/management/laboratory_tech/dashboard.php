<?php
// dashboard_laboratory_tech.php
// Using the same approach as admin dashboard for consistency
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Authentication check - refactored to eliminate redirect loops
// Check 1: Is the user logged in at all?
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    // User is not logged in - redirect to login
    error_log('Laboratory Tech Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'laboratory_tech') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to laboratory tech dashboard - User: ' . $_SESSION['employee_id'] . ' with role: ' . 
              ($_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}


// Log session data for debugging
error_log('Laboratory Tech Dashboard - Session Data: ' . print_r($_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/staff_assignment.php';

// Debug connection status
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Check staff assignment for today (non-blocking)
$assignment = null;
$assignment_warning = '';
try {
    $assignment = getStaffAssignment($employee_id);
    if (!$assignment) {
        // Not assigned today, but allow access with warning
        error_log('Laboratory Tech Dashboard: No active staff assignment for today - allowing access with warning.');
        $assignment_warning = 'You are not assigned to any station today. Some queue management features may be limited. Please contact the administrator if you need station access.';
    }
} catch (Exception $e) {
    // Staff assignment function failed, log error but continue
    error_log('Laboratory Tech Dashboard: Staff assignment check failed: ' . $e->getMessage());
    $assignment_warning = 'Unable to verify station assignment. Some features may be limited.';
}

// -------------------- Data bootstrap (Laboratory Tech Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'pending_tests' => 0,
        'completed_today' => 0,
        'equipment_active' => 0,
        'samples_collected' => 0,
        'results_pending_review' => 0,
        'total_tests_month' => 0
    ],
    'pending_tests' => [],
    'recent_results' => [],
    'equipment_status' => [],
    'lab_alerts' => []
];

// Get lab tech info from employees table
try {
    $stmt = $pdo->prepare('SELECT first_name, middle_name, last_name, employee_number, role FROM employees WHERE employee_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $full_name = $row['first_name'];
        if (!empty($row['middle_name'])) $full_name .= ' ' . $row['middle_name'];
        $full_name .= ' ' . $row['last_name'];
        $defaults['name'] = trim($full_name);
        $defaults['employee_number'] = $row['employee_number'];
        $defaults['role'] = $row['role'];
    }
} catch (PDOException $e) {
    error_log("Laboratory tech dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Pending Tests
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE status = "pending" AND assigned_tech_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_tests'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Tests Completed Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE DATE(completed_date) = ? AND assigned_tech_id = ? AND status = "completed"');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['completed_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Active Equipment
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_equipment WHERE status = "active" AND assigned_tech_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['equipment_active'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Samples Collected Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_samples WHERE DATE(collection_date) = ? AND collected_by = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['samples_collected'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Results Pending Review
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE status = "results_ready" AND assigned_tech_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['results_pending_review'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Total Tests This Month
    $month_start = date('Y-m-01');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE created_date >= ? AND assigned_tech_id = ?');
    $stmt->execute([$month_start, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['total_tests_month'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Pending Tests
try {
    $stmt = $pdo->prepare('
        SELECT lt.test_id, lt.test_type, lt.priority, lt.order_date, 
               p.first_name, p.last_name, p.patient_id, lt.specimen_type
        FROM lab_tests lt 
        JOIN patients p ON lt.patient_id = p.patient_id 
        WHERE lt.status = "pending" AND lt.assigned_tech_id = ? 
        ORDER BY lt.priority DESC, lt.order_date ASC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['pending_tests'][] = [
            'test_id' => $row['test_id'],
            'test_type' => $row['test_type'] ?? 'Lab Test',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'priority' => $row['priority'] ?? 'normal',
            'order_date' => date('M d, Y', strtotime($row['order_date'])),
            'specimen_type' => $row['specimen_type'] ?? 'Blood'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['pending_tests'] = [
        ['test_id' => '-', 'test_type' => 'No pending tests', 'patient_name' => '-', 'patient_id' => '-', 'priority' => 'normal', 'order_date' => '-', 'specimen_type' => '-']
    ];
}

// Recent Results
try {
    $stmt = $pdo->prepare('
        SELECT lt.test_id, lt.test_type, lt.completed_date, 
               p.first_name, p.last_name, p.patient_id, lt.status
        FROM lab_tests lt 
        JOIN patients p ON lt.patient_id = p.patient_id 
        WHERE lt.assigned_tech_id = ? AND lt.status IN ("completed", "results_ready") 
        ORDER BY lt.completed_date DESC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['recent_results'][] = [
            'test_id' => $row['test_id'],
            'test_type' => $row['test_type'] ?? 'Lab Test',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'completed_date' => date('M d, Y H:i', strtotime($row['completed_date'])),
            'status' => $row['status'] ?? 'completed'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['recent_results'] = [
        ['test_id' => '-', 'test_type' => 'No recent results', 'patient_name' => '-', 'patient_id' => '-', 'completed_date' => '-', 'status' => 'completed']
    ];
}

// Equipment Status
try {
    $stmt = $pdo->prepare('
        SELECT equipment_id, equipment_name, status, last_maintenance, next_maintenance
        FROM lab_equipment 
        WHERE assigned_tech_id = ? OR assigned_tech_id IS NULL
        ORDER BY status DESC, next_maintenance ASC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['equipment_status'][] = [
            'equipment_id' => $row['equipment_id'],
            'equipment_name' => $row['equipment_name'] ?? 'Lab Equipment',
            'status' => $row['status'] ?? 'active',
            'last_maintenance' => $row['last_maintenance'] ? date('M d, Y', strtotime($row['last_maintenance'])) : 'N/A',
            'next_maintenance' => $row['next_maintenance'] ? date('M d, Y', strtotime($row['next_maintenance'])) : 'N/A'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['equipment_status'] = [
        ['equipment_id' => 'EQ001', 'equipment_name' => 'Microscope', 'status' => 'active', 'last_maintenance' => 'Sep 01, 2025', 'next_maintenance' => 'Dec 01, 2025'],
        ['equipment_id' => 'EQ002', 'equipment_name' => 'Centrifuge', 'status' => 'active', 'last_maintenance' => 'Aug 15, 2025', 'next_maintenance' => 'Nov 15, 2025'],
        ['equipment_id' => 'EQ003', 'equipment_name' => 'Analyzer', 'status' => 'maintenance', 'last_maintenance' => 'Jul 20, 2025', 'next_maintenance' => 'Oct 20, 2025']
    ];
}

// Lab Alerts
try {
    $stmt = $pdo->prepare('
        SELECT la.alert_type, la.message, la.created_at, la.priority,
               p.first_name, p.last_name, p.patient_id
        FROM lab_alerts la 
        LEFT JOIN patients p ON la.patient_id = p.patient_id 
        WHERE la.tech_id = ? AND la.status = "active" 
        ORDER BY la.priority DESC, la.created_at DESC 
        LIMIT 4
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['lab_alerts'][] = [
            'alert_type' => $row['alert_type'] ?? 'general',
            'message' => $row['message'] ?? '',
            'patient_name' => $row['patient_id'] ? trim($row['first_name'] . ' ' . $row['last_name']) : 'System',
            'patient_id' => $row['patient_id'] ?? '-',
            'priority' => $row['priority'] ?? 'normal',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default alerts
    $defaults['lab_alerts'] = [
        ['alert_type' => 'info', 'message' => 'Equipment maintenance reminder: Centrifuge due for calibration', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'normal', 'date' => date('m/d/Y H:i')],
        ['alert_type' => 'warning', 'message' => 'Quality control test needed for Chemistry Analyzer', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'high', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Laboratory Tech Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Use absolute paths for all styles -->
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/sidebar.css">
    <style>
        :root {
            --primary: #17a2b8;
            --primary-light: #5dbedb;
            --primary-dark: #138496;
            --secondary: #6c757d;
            --secondary-light: #adb5bd;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --light-hover: #e2e6ea;
            --dark: #343a40;
            --white: #ffffff;
            --border: #dee2e6;
            --border-light: #f1f1f1;
            --shadow-sm: 0 .125rem .25rem rgba(0,0,0,.075);
            --shadow: 0 .5rem 1rem rgba(0,0,0,.08);
            --shadow-lg: 0 1rem 3rem rgba(0,0,0,.1);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
            --card-hover-y: -4px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #e1f5fe 0%, #b3e5fc 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .content-wrapper {
            margin-left: var(--sidebar-width, 280px);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
                margin-top: 70px;
            }
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-message h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin: 0;
            color: var(--primary-dark);
            line-height: 1.2;
        }

        .welcome-message p {
            margin: 0.5rem 0 0;
            color: var(--secondary);
            font-size: 1rem;
        }

        .dashboard-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background: var(--dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Info Card */
        .info-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .info-card h2 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-card p {
            margin: 0;
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .welcome-message h1 {
                font-size: 1.8rem;
            }
            
            .dashboard-actions {
                justify-content: center;
                flex-wrap: wrap;
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

        .stat-card.pending { --card-color: #ffc107; }
        .stat-card.completed { --card-color: #28a745; }
        .stat-card.equipment { --card-color: #17a2b8; }
        .stat-card.samples { --card-color: #6f42c1; }
        .stat-card.results { --card-color: #fd7e14; }
        .stat-card.monthly { --card-color: #007bff; }

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

        /* Quick Actions */
        .quick-actions-section {
            margin-bottom: 2.5rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            text-decoration: none;
            color: var(--dark);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--border-light);
            position: relative;
        }

        .action-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--card-color, var(--primary));
            transition: width 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(var(--card-hover-y));
            box-shadow: var(--shadow);
        }

        .action-card:hover::before {
            width: 8px;
        }

        .action-icon {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(var(--card-color-rgb, 23, 162, 184), 0.1);
            color: var(--card-color, var(--primary));
            border-radius: 12px;
            flex-shrink: 0;
        }

        .action-content {
            flex-grow: 1;
        }

        .action-content h3 {
            margin: 0 0 0.35rem;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-content p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .action-arrow {
            color: var(--card-color, var(--primary));
            font-size: 1rem;
            opacity: 0.7;
            transition: transform 0.2s;
        }

        .action-card:hover .action-arrow {
            transform: translateX(4px);
            opacity: 1;
        }

        .action-card.blue { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .action-card.teal { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
        .action-card.purple { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }
        .action-card.orange { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .action-card.green { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .action-card.red { --card-color: #dc3545; --card-color-rgb: 220, 53, 69; }

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

        .lab-table {
            width: 100%;
            border-collapse: collapse;
        }

        .lab-table th,
        .lab-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .lab-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .lab-table td {
            color: var(--secondary);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-urgent {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-high {
            background: #fff3cd;
            color: #856404;
        }

        .priority-normal {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-results_ready {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-critical {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Test Item */
        .test-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .test-name {
            font-weight: 600;
            color: var(--dark);
        }

        .test-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Equipment Item */
        .equipment-item {
            padding: 0.75rem;
            border-left: 3px solid #17a2b8;
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .equipment-name {
            font-weight: 600;
            color: var(--dark);
        }

        .equipment-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
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
    include $root_path . '/includes/sidebar_laboratory_tech.php';
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>Laboratory Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="../laboratory/sample_collection.php" class="btn btn-primary">
                    <i class="fas fa-vial"></i> Collect Samples
                </a>
                <a href="../laboratory/test_processing.php" class="btn btn-secondary">
                    <i class="fas fa-flask"></i> Process Tests
                </a>
                <a href="../auth/employee_logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>        </section>
        
        <!-- Assignment Warning (if applicable) -->
        <?php if (!empty($assignment_warning)): ?>
        <section class="info-card" style="border-left-color: #ffc107; background: #fff3cd;">
            <h2 style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> Station Assignment Notice</h2>
            <p style="color: #856404;"><?php echo htmlspecialchars($assignment_warning); ?></p>
        </section>
        <?php endif; ?>
        
        <!-- System Overview Card --> <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-microscope"></i> Laboratory Services Overview</h2>
            <p>Welcome to your laboratory dashboard. Here you can manage sample collection, test processing, result entry, quality control procedures, and equipment maintenance operations.</p>
        </section>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Laboratory Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_tests']); ?></div>
                <div class="stat-label">Pending Tests</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['completed_today']); ?></div>
                <div class="stat-label">Completed Today</div>
            </div>
            <div class="stat-card equipment">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-cogs"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['equipment_active']); ?></div>
                <div class="stat-label">Active Equipment</div>
            </div>
            <div class="stat-card samples">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-vial"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['samples_collected']); ?></div>
                <div class="stat-label">Samples Collected</div>
            </div>
            <div class="stat-card results">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['results_pending_review']); ?></div>
                <div class="stat-label">Results Pending</div>
            </div>
            <div class="stat-card monthly">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_tests_month']); ?></div>
                <div class="stat-label">Tests This Month</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="quick-actions-section">
            <div class="action-grid">
                <a href="../laboratory/sample_collection.php" class="action-card purple">
                    <div class="action-icon">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div class="action-content">
                        <h3>Collect Samples</h3>
                        <p>Register and collect patient samples for testing</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../laboratory/test_processing.php" class="action-card teal">
                    <div class="action-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="action-content">
                        <h3>Process Tests</h3>
                        <p>Run laboratory tests and record results</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../laboratory/results_entry.php" class="action-card blue">
                    <div class="action-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="action-content">
                        <h3>Enter Results</h3>
                        <p>Input test results and generate reports</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../laboratory/quality_control.php" class="action-card orange">
                    <div class="action-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="action-content">
                        <h3>Quality Control</h3>
                        <p>Perform quality control checks and validations</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../laboratory/equipment_maintenance.php" class="action-card green">
                    <div class="action-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="action-content">
                        <h3>Equipment Maintenance</h3>
                        <p>Maintain and calibrate laboratory equipment</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../laboratory/inventory.php" class="action-card red">
                    <div class="action-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="action-content">
                        <h3>Lab Inventory</h3>
                        <p>Manage reagents, supplies, and consumables</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Pending Tests -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-hourglass-half"></i> Pending Tests</h3>
                        <a href="../laboratory/pending_tests.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['pending_tests']) && $defaults['pending_tests'][0]['test_type'] !== 'No pending tests'): ?>
                        <div class="table-wrapper">
                            <table class="lab-table">
                                <thead>
                                    <tr>
                                        <th>Test</th>
                                        <th>Patient</th>
                                        <th>Priority</th>
                                        <th>Ordered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['pending_tests'] as $test): ?>
                                        <tr>
                                            <td>
                                                <div class="test-name"><?php echo htmlspecialchars($test['test_type']); ?></div>
                                                <small><?php echo htmlspecialchars($test['specimen_type']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($test['patient_name']); ?></div>
                                                <small><?php echo htmlspecialchars($test['patient_id']); ?></small>
                                            </td>
                                            <td><span class="status-badge priority-<?php echo $test['priority']; ?>"><?php echo ucfirst($test['priority']); ?></span></td>
                                            <td><?php echo htmlspecialchars($test['order_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending tests at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Results -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-clipboard-check"></i> Recent Results</h3>
                        <a href="../laboratory/test_results.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['recent_results']) && $defaults['recent_results'][0]['test_type'] !== 'No recent results'): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['recent_results'] as $result): ?>
                                <div class="test-item">
                                    <div class="test-name">
                                        <?php echo htmlspecialchars($result['test_type']); ?>
                                        <span class="status-badge status-<?php echo $result['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $result['status'])); ?></span>
                                    </div>
                                    <div class="test-details">
                                        Patient: <?php echo htmlspecialchars($result['patient_name']); ?> • 
                                        ID: <?php echo htmlspecialchars($result['patient_id']); ?> • 
                                        Completed: <?php echo htmlspecialchars($result['completed_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <p>No recent test results</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Equipment Status -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-cogs"></i> Equipment Status</h3>
                        <a href="../laboratory/equipment_status.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['equipment_status'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['equipment_status'] as $equipment): ?>
                                <div class="equipment-item">
                                    <div class="equipment-name">
                                        <?php echo htmlspecialchars($equipment['equipment_name']); ?>
                                        <span class="status-badge status-<?php echo $equipment['status']; ?>"><?php echo ucfirst($equipment['status']); ?></span>
                                    </div>
                                    <div class="equipment-details">
                                        ID: <?php echo htmlspecialchars($equipment['equipment_id']); ?> • 
                                        Last Maintenance: <?php echo htmlspecialchars($equipment['last_maintenance']); ?> • 
                                        Next Due: <?php echo htmlspecialchars($equipment['next_maintenance']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tools"></i>
                            <p>No equipment assigned</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Lab Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Lab Alerts</h3>
                        <a href="../laboratory/lab_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['lab_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['lab_alerts'] as $alert): ?>
                                <div class="test-item">
                                    <div class="test-name">
                                        <?php echo htmlspecialchars($alert['patient_name']); ?>
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </div>
                                    <div class="test-details">
                                        <?php echo htmlspecialchars($alert['message']); ?><br>
                                        <small><?php echo htmlspecialchars($alert['date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No lab alerts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
