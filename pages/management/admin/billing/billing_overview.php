<?php
// Admin Billing Overview - Administrative access to billing system
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in and has admin privileges
if (!is_employee_logged_in()) {
    header("Location: ../../auth/employee_login.php");
    exit();
}

$employee_role = get_employee_session('role');
if (!in_array($employee_role, ['admin'])) {
    header("Location: ../dashboard.php?error=Access denied");
    exit();
}

$employee_id = get_employee_session('employee_id');
$employee_name = get_employee_session('employee_name', 'Unknown User');

// Include sidebar for admin
include '../../../../includes/sidebar_admin.php';

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Get billing system statistics for admin overview
$stats = [];
try {
    // Get overall billing statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_invoices,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_invoices,
            COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_invoices,
            COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN total_amount END), 0) as total_outstanding,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' AND DATE(billing_date) = CURDATE() THEN total_amount END), 0) as today_collections,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' AND YEAR(billing_date) = YEAR(CURDATE()) AND MONTH(billing_date) = MONTH(CURDATE()) THEN total_amount END), 0) as month_collections
        FROM billing
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent activity
    $stmt = $pdo->query("
        SELECT 
            r.receipt_id,
            r.receipt_number,
            r.payment_date,
            r.amount_paid,
            r.payment_method,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            CONCAT(e.first_name, ' ', e.last_name) as cashier_name
        FROM receipts r
        JOIN billing b ON r.billing_id = b.billing_id
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN employees e ON r.received_by_employee_id = e.employee_id
        ORDER BY r.payment_date DESC
        LIMIT 10
    ");
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Admin billing overview error: " . $e->getMessage());
    $error = "Failed to load billing statistics.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing System Overview - CHO Koronadal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    <style>
        .billing-overview {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .billing-overview {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.2rem;
            color: #0077b6;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .page-header p {
            margin: 0.5rem 0 0 0;
            color: #6c757d;
            font-size: 1rem;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0 0 1rem 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .breadcrumb a {
            color: #6c757d;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: #0077b6;
        }

        .action-buttons {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: #007BFF;
            color: #fff;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #16a085, #0f6b5c);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #0f6b5c, #0a4f44);
            transform: translateY(-2px);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            color: inherit;
            text-decoration: none;
        }

        .action-icon {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .action-content h3 {
            margin: 0 0 0.5rem 0;
            color: #0077b6;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .action-content p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .action-arrow {
            margin-left: auto;
            color: #0077b6;
            font-size: 1.2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.outstanding {
            border-left-color: #dc3545;
        }

        .stat-card.collections {
            border-left-color: #28a745;
        }

        .stat-card.total {
            border-left-color: #007bff;
        }

        .stat-card.monthly {
            border-left-color: #ffc107;
        }

        .stat-icon {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
        }

        .stat-content h3 {
            margin: 0 0 0.3rem 0;
            font-size: 0.95rem;
            color: #495057;
            font-weight: 600;
        }

        .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0077b6;
            margin: 0.3rem 0;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.8rem;
            margin: 0;
        }

        .section-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-icon {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .section-title {
            margin: 0;
            font-size: 1.5rem;
            color: #0077b6;
            font-weight: 600;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th,
        .activity-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }

        .activity-table thead {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .activity-table th {
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border: none;
        }

        .activity-table tbody tr {
            transition: all 0.2s ease;
        }

        .activity-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .activity-table tbody tr:last-child td {
            border-bottom: none;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert i {
            font-size: 1.2rem;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            margin-left: auto;
            opacity: 0.7;
        }

        .btn-close:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <section class="billing-overview">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Admin Dashboard</a>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">Billing System Overview</span>
        </div>

        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-file-invoice-dollar" style="margin-right: 0.5rem;"></i>Billing System Overview</h1>
                <p>Comprehensive financial overview and management dashboard</p>
            </div>
            <div class="action-buttons">
                <a href="../../cashier/billing/billing_management.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Full Billing Dashboard
                </a>
                <a href="../../cashier/billing/billing_reports.php" class="btn btn-secondary">
                    <i class="fas fa-chart-bar"></i> Financial Reports
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Billing System Statistics -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h2 class="section-title">Financial Overview</h2>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card outstanding">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Outstanding Bills</h3>
                        <p class="stat-number">₱<?php echo number_format($stats['total_outstanding'] ?? 0, 2); ?></p>
                        <p class="stat-label"><?php echo $stats['unpaid_invoices'] ?? 0; ?> unpaid invoices</p>
                    </div>
                </div>

                <div class="stat-card collections">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Today's Collections</h3>
                        <p class="stat-number">₱<?php echo number_format($stats['today_collections'] ?? 0, 2); ?></p>
                        <p class="stat-label">collected today</p>
                    </div>
                </div>

                <div class="stat-card monthly">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                        <i class="fas fa-calendar-month"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Monthly Collections</h3>
                        <p class="stat-number">₱<?php echo number_format($stats['month_collections'] ?? 0, 2); ?></p>
                        <p class="stat-label">this month</p>
                    </div>
                </div>

                <div class="stat-card total">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #0056b3);">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Invoices</h3>
                        <p class="stat-number"><?php echo $stats['total_invoices'] ?? 0; ?></p>
                        <p class="stat-label">all time</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <h2 class="section-title">Quick Actions</h2>
            </div>
            
            <div class="quick-actions">
                <a href="../../cashier/billing/billing_management.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="action-content">
                        <h3>Billing Dashboard</h3>
                        <p>Access full billing management system</p>
                    </div>
                    <i class="fas fa-arrow-right action-arrow"></i>
                </a>

                <a href="../../cashier/billing/create_invoice.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-content">
                        <h3>Create Invoice</h3>
                        <p>Generate new patient invoices</p>
                    </div>
                    <i class="fas fa-arrow-right action-arrow"></i>
                </a>

                <a href="../../cashier/billing/process_payment.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="action-content">
                        <h3>Process Payment</h3>
                        <p>Handle patient payments and receipts</p>
                    </div>
                    <i class="fas fa-arrow-right action-arrow"></i>
                </a>

                <a href="../../cashier/billing/invoice_search.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="action-content">
                        <h3>Search Invoices</h3>
                        <p>Find and manage existing invoices</p>
                    </div>
                    <i class="fas fa-arrow-right action-arrow"></i>
            </a>

            <a href="../../cashier/billing/billing_reports.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-content">
                    <h3>Financial Reports</h3>
                    <p>View analytics and generate reports</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </a>

            <a href="../../cashier/billing/print_receipt.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-print"></i>
                </div>
                <div class="action-content">
                    <h3>Print Receipts</h3>
                    <p>Reprint receipts and manage printing</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
                </a>
            </div>
        </div>

        <!-- Recent Payment Activity -->
        <?php if (!empty($recent_activity)): ?>
            <div class="section-container">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h2 class="section-title">Recent Payment Activity</h2>
                </div>

                <div class="table-container">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Patient</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Cashier</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                                <tr>
                                    <td>
                                        <strong style="color: #0077b6;"><?php echo htmlspecialchars($activity['receipt_number']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['patient_name']); ?></td>
                                    <td>
                                        <strong style="color: #28a745;">₱<?php echo number_format($activity['amount_paid'], 2); ?></strong>
                                    </td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $activity['payment_method'])); ?></td>
                                    <td><?php echo htmlspecialchars($activity['cashier_name'] ?: 'System'); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($activity['payment_date'])); ?>
                                        <br><small style="color: #6c757d;"><?php echo date('g:i A', strtotime($activity['payment_date'])); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>


    <script>
        // Auto-dismiss alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>

</html>