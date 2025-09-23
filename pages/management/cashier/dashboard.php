<?php
// dashboard_cashier.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not a cashier, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

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
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #28a745;
            --primary-dark: #1e7e34;
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

        body {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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

        .action-card.green { --card-color: #28a745; }
        .action-card.blue { --card-color: #007bff; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.purple { --card-color: #6f42c1; }
        .action-card.teal { --card-color: #17a2b8; }
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
    include '../../includes/sidebar_cashier.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                Cashier Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

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
        <div class="action-grid">
            <a href="../billing/process_payment.php" class="action-card green">
                <i class="fas fa-cash-register icon"></i>
                <h3>Process Payment</h3>
                <p>Accept and process patient payments</p>
            </a>
            <a href="../billing/generate_invoice.php" class="action-card blue">
                <i class="fas fa-file-invoice icon"></i>
                <h3>Generate Invoice</h3>
                <p>Create and print patient invoices</p>
            </a>
            <a href="../billing/payment_history.php" class="action-card purple">
                <i class="fas fa-history icon"></i>
                <h3>Payment History</h3>
                <p>View and manage payment records</p>
            </a>
            <a href="../billing/outstanding_bills.php" class="action-card orange">
                <i class="fas fa-exclamation-circle icon"></i>
                <h3>Outstanding Bills</h3>
                <p>Manage overdue and pending payments</p>
            </a>
            <a href="../billing/financial_reports.php" class="action-card teal">
                <i class="fas fa-chart-bar icon"></i>
                <h3>Financial Reports</h3>
                <p>Generate revenue and payment reports</p>
            </a>
            <a href="../billing/refunds.php" class="action-card red">
                <i class="fas fa-undo icon"></i>
                <h3>Refunds & Adjustments</h3>
                <p>Process refunds and billing adjustments</p>
            </a>
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
    </section>
</body>

</html>
