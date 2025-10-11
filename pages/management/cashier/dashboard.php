<?php
// dashboard_cashier.php
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
    error_log('Cashier Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'cashier') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to cashier dashboard - User: ' . $_SESSION['employee_id'] . ' with role: ' . 
              ($_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}


// Log session data for debugging
error_log('Cashier Dashboard - Session Data: ' . print_r($_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/staff_assignment.php';

// Debug connection status
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Enforce staff assignment for today
$assignment = getStaffAssignment($employee_id);
if (!$assignment) {
    // Not assigned today, block access
    error_log('Cashier Dashboard: No active staff assignment for today.');
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'You are not assigned to any station today. Please contact the administrator.');
    header('Location: ../auth/employee_login.php?not_assigned=1');
    exit();
}

// -------------------- Data bootstrap (Cashier Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'pending_payments' => 0,
        'payments_today' => 0,
        'revenue_today' => 0,
        'outstanding_balances' => 0,
        'transactions_today' => 0,
        'revenue_month' => 0
    ],
    'pending_payments' => [],
    'recent_transactions' => [],
    'outstanding_bills' => [],
    'billing_alerts' => []
];

// Get cashier info from employees table
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
    error_log("Cashier dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Pending Payments
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM billing WHERE status = "pending" AND cashier_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_payments'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Payments Processed Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = ? AND cashier_id = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['payments_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Revenue Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM payments WHERE DATE(payment_date) = ? AND cashier_id = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['revenue_today'] = $row['total'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Outstanding Balances
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM billing WHERE status = "outstanding" AND total_amount > 0');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['outstanding_balances'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Transactions Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM billing WHERE DATE(billing_date) = ? AND cashier_id = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['transactions_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Revenue This Month
    $month_start = date('Y-m-01');
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM payments WHERE payment_date >= ? AND cashier_id = ?');
    $stmt->execute([$month_start, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['revenue_month'] = $row['total'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Pending Payments
try {
    $stmt = $pdo->prepare('
        SELECT b.billing_id, b.service_type, b.total_amount, b.billing_date,
               p.first_name, p.last_name, p.patient_id, b.priority
        FROM billing b 
        JOIN patients p ON b.patient_id = p.patient_id 
        WHERE b.status = "pending" AND b.cashier_id = ? 
        ORDER BY b.priority DESC, b.billing_date ASC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['pending_payments'][] = [
            'billing_id' => $row['billing_id'],
            'service_type' => $row['service_type'] ?? 'Medical Service',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'total_amount' => $row['total_amount'] ?? 0,
            'priority' => $row['priority'] ?? 'normal',
            'billing_date' => date('M d, Y', strtotime($row['billing_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['pending_payments'] = [
        ['billing_id' => '-', 'service_type' => 'No pending payments', 'patient_name' => '-', 'patient_id' => '-', 'total_amount' => 0, 'priority' => 'normal', 'billing_date' => '-']
    ];
}

// Recent Transactions
try {
    $stmt = $pdo->prepare('
        SELECT py.payment_id, py.payment_method, py.amount, py.payment_date,
               p.first_name, p.last_name, p.patient_id, b.service_type
        FROM payments py 
        JOIN billing b ON py.billing_id = b.billing_id
        JOIN patients p ON b.patient_id = p.patient_id 
        WHERE py.cashier_id = ? 
        ORDER BY py.payment_date DESC 
        LIMIT 6
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['recent_transactions'][] = [
            'payment_id' => $row['payment_id'],
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'service_type' => $row['service_type'] ?? 'Medical Service',
            'amount' => $row['amount'] ?? 0,
            'payment_method' => $row['payment_method'] ?? 'Cash',
            'payment_date' => date('M d, Y H:i', strtotime($row['payment_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['recent_transactions'] = [
        ['payment_id' => '-', 'patient_name' => 'No recent transactions', 'patient_id' => '-', 'service_type' => '-', 'amount' => 0, 'payment_method' => '-', 'payment_date' => '-']
    ];
}

// Outstanding Bills
try {
    $stmt = $pdo->prepare('
        SELECT b.billing_id, b.service_type, b.total_amount, b.billing_date,
               p.first_name, p.last_name, p.patient_id, 
               DATEDIFF(CURDATE(), b.billing_date) as days_overdue
        FROM billing b 
        JOIN patients p ON b.patient_id = p.patient_id 
        WHERE b.status = "outstanding" AND b.total_amount > 0
        ORDER BY days_overdue DESC, b.total_amount DESC 
        LIMIT 5
    ');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['outstanding_bills'][] = [
            'billing_id' => $row['billing_id'],
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'service_type' => $row['service_type'] ?? 'Medical Service',
            'total_amount' => $row['total_amount'] ?? 0,
            'billing_date' => date('M d, Y', strtotime($row['billing_date'])),
            'days_overdue' => $row['days_overdue'] ?? 0
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['outstanding_bills'] = [
        ['billing_id' => 'B001', 'patient_name' => 'Sample Patient', 'patient_id' => 'P001', 'service_type' => 'Consultation', 'total_amount' => 500, 'billing_date' => 'Sep 15, 2025', 'days_overdue' => 6],
        ['billing_id' => 'B002', 'patient_name' => 'Another Patient', 'patient_id' => 'P002', 'service_type' => 'Laboratory Test', 'total_amount' => 750, 'billing_date' => 'Sep 10, 2025', 'days_overdue' => 11]
    ];
}

// Billing Alerts
try {
    $stmt = $pdo->prepare('
        SELECT ba.alert_type, ba.message, ba.created_at, ba.priority,
               p.first_name, p.last_name, p.patient_id
        FROM billing_alerts ba 
        LEFT JOIN patients p ON ba.patient_id = p.patient_id 
        WHERE ba.cashier_id = ? AND ba.status = "active" 
        ORDER BY ba.priority DESC, ba.created_at DESC 
        LIMIT 4
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['billing_alerts'][] = [
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
    $defaults['billing_alerts'] = [
        ['alert_type' => 'warning', 'message' => 'Payment overdue: Patient has outstanding balance over 30 days', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'high', 'date' => date('m/d/Y H:i')],
        ['alert_type' => 'info', 'message' => 'Daily cash reconciliation pending', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'normal', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Cashier Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Use absolute paths for consistency -->
    <link rel="stylesheet" href="<?= $root_path ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= $root_path ?>/assets/css/sidebar.css">
    <style>
        :root {
            --primary: #28a745;
            --primary-light: #34ce57;
            --primary-dark: #1e7e34;
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
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
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
        .stat-card.payments { --card-color: #28a745; }
        .stat-card.revenue { --card-color: #007bff; }
        .stat-card.outstanding { --card-color: #dc3545; }
        .stat-card.transactions { --card-color: #6f42c1; }
        .stat-card.monthly { --card-color: #17a2b8; }

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
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
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
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
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
            background: rgba(var(--card-color-rgb, 40, 167, 69), 0.1);
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

        .action-card.green { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .action-card.blue { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .action-card.orange { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .action-card.purple { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }
        .action-card.teal { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
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

        .billing-table {
            width: 100%;
            border-collapse: collapse;
        }

        .billing-table th,
        .billing-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .billing-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .billing-table td {
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

        .overdue-critical {
            background: #f8d7da;
            color: #721c24;
        }

        .overdue-warning {
            background: #fff3cd;
            color: #856404;
        }

        .overdue-normal {
            background: #d4edda;
            color: #155724;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Payment Item */
        .payment-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .payment-name {
            font-weight: 600;
            color: var(--dark);
        }

        .payment-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Bill Item */
        .bill-item {
            padding: 0.75rem;
            border-left: 3px solid #dc3545;
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .bill-name {
            font-weight: 600;
            color: var(--dark);
        }

        .bill-details {
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

        /* Currency styling */
        .currency {
            font-weight: 600;
            color: var(--primary);
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
    include $root_path . '/includes/sidebar_cashier.php';
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>Cashier Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="../billing/process_payment.php" class="btn btn-primary">
                    <i class="fas fa-cash-register"></i> Process Payment
                </a>
                <a href="../billing/generate_invoice.php" class="btn btn-secondary">
                    <i class="fas fa-file-invoice"></i> Generate Invoice
                </a>
                <a href="../auth/employee_logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </section>
        
        <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-cash-register"></i> Billing & Payment Overview</h2>
            <p>Welcome to your cashier dashboard. Here you can process payments, generate invoices, manage outstanding bills, and access comprehensive financial reporting tools.</p>
        </section>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Financial Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_payments']); ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
            <div class="stat-card payments">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['payments_today']); ?></div>
                <div class="stat-label">Payments Today</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                </div>
                <div class="stat-number">₱<?php echo number_format($defaults['stats']['revenue_today'], 2); ?></div>
                <div class="stat-label">Revenue Today</div>
            </div>
            <div class="stat-card outstanding">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['outstanding_balances']); ?></div>
                <div class="stat-label">Outstanding Bills</div>
            </div>
            <div class="stat-card transactions">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['transactions_today']); ?></div>
                <div class="stat-label">Transactions Today</div>
            </div>
            <div class="stat-card monthly">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
                <div class="stat-number">₱<?php echo number_format($defaults['stats']['revenue_month'], 2); ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="quick-actions-section">
            <div class="action-grid">
                <a href="../billing/process_payment.php" class="action-card green">
                    <div class="action-icon">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <div class="action-content">
                        <h3>Process Payment</h3>
                        <p>Accept and process patient payments</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../billing/generate_invoice.php" class="action-card blue">
                    <div class="action-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="action-content">
                        <h3>Generate Invoice</h3>
                        <p>Create and print patient invoices</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../billing/payment_history.php" class="action-card purple">
                    <div class="action-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="action-content">
                        <h3>Payment History</h3>
                        <p>View and manage payment records</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../billing/outstanding_bills.php" class="action-card orange">
                    <div class="action-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="action-content">
                        <h3>Outstanding Bills</h3>
                        <p>Manage overdue and pending payments</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../billing/financial_reports.php" class="action-card teal">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-content">
                        <h3>Financial Reports</h3>
                        <p>Generate revenue and payment reports</p>
                    </div>
                    <div class="action-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <a href="../billing/refunds.php" class="action-card red">
                    <div class="action-icon">
                        <i class="fas fa-undo"></i>
                    </div>
                    <div class="action-content">
                        <h3>Refunds & Adjustments</h3>
                        <p>Process refunds and billing adjustments</p>
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
                <!-- Pending Payments -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-clock"></i> Pending Payments</h3>
                        <a href="../billing/pending_payments.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['pending_payments']) && $defaults['pending_payments'][0]['service_type'] !== 'No pending payments'): ?>
                        <div class="table-wrapper">
                            <table class="billing-table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Patient</th>
                                        <th>Amount</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['pending_payments'] as $payment): ?>
                                        <tr>
                                            <td>
                                                <div class="payment-name"><?php echo htmlspecialchars($payment['service_type']); ?></div>
                                                <small><?php echo htmlspecialchars($payment['billing_date']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($payment['patient_name']); ?></div>
                                                <small><?php echo htmlspecialchars($payment['patient_id']); ?></small>
                                            </td>
                                            <td><span class="currency">₱<?php echo number_format($payment['total_amount'], 2); ?></span></td>
                                            <td><span class="status-badge priority-<?php echo $payment['priority']; ?>"><?php echo ucfirst($payment['priority']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending payments at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Transactions -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-receipt"></i> Recent Transactions</h3>
                        <a href="../billing/transaction_history.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['recent_transactions']) && $defaults['recent_transactions'][0]['patient_name'] !== 'No recent transactions'): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['recent_transactions'] as $transaction): ?>
                                <div class="payment-item">
                                    <div class="payment-name">
                                        <?php echo htmlspecialchars($transaction['patient_name']); ?>
                                        <span class="currency">₱<?php echo number_format($transaction['amount'], 2); ?></span>
                                    </div>
                                    <div class="payment-details">
                                        Service: <?php echo htmlspecialchars($transaction['service_type']); ?> • 
                                        Method: <?php echo htmlspecialchars($transaction['payment_method']); ?> • 
                                        Date: <?php echo htmlspecialchars($transaction['payment_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No recent transactions</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Outstanding Bills -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Outstanding Bills</h3>
                        <a href="../billing/outstanding_reports.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['outstanding_bills'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['outstanding_bills'] as $bill): ?>
                                <div class="bill-item">
                                    <div class="bill-name">
                                        <?php echo htmlspecialchars($bill['patient_name']); ?>
                                        <span class="currency">₱<?php echo number_format($bill['total_amount'], 2); ?></span>
                                        <?php 
                                        $overdue_status = 'normal';
                                        if ($bill['days_overdue'] > 30) $overdue_status = 'critical';
                                        elseif ($bill['days_overdue'] > 14) $overdue_status = 'warning';
                                        ?>
                                        <span class="status-badge overdue-<?php echo $overdue_status; ?>"><?php echo $bill['days_overdue']; ?> days</span>
                                    </div>
                                    <div class="bill-details">
                                        Service: <?php echo htmlspecialchars($bill['service_type']); ?> • 
                                        ID: <?php echo htmlspecialchars($bill['patient_id']); ?> • 
                                        Billed: <?php echo htmlspecialchars($bill['billing_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No outstanding bills</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Billing Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-bell"></i> Billing Alerts</h3>
                        <a href="../billing/billing_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['billing_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['billing_alerts'] as $alert): ?>
                                <div class="payment-item">
                                    <div class="payment-name">
                                        <?php echo htmlspecialchars($alert['patient_name']); ?>
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </div>
                                    <div class="payment-details">
                                        <?php echo htmlspecialchars($alert['message']); ?><br>
                                        <small><?php echo htmlspecialchars($alert['date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No billing alerts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
