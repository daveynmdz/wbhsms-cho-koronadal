<?php
// Billing Reports - Financial Analytics and Dashboard
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in and has cashier/admin privileges
if (!is_employee_logged_in()) {
    header("Location: ../../auth/employee_login.php");
    exit();
}

$employee_role = get_employee_session('role');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    header("Location: ../../dashboard.php?error=Access denied");
    exit();
}

$employee_id = get_employee_session('employee_id');
$employee_name = get_employee_session('first_name') . ' ' . get_employee_session('last_name');

// Include appropriate sidebar based on user role
if ($employee_role === 'admin') {
    include '../../../../includes/sidebar_admin.php';
} else {
    include '../../../../includes/sidebar_cashier.php';
}

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Get current date ranges
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');
$year_start = date('Y-01-01');

try {
    // Daily collections
    $stmt = $pdo->prepare("
        SELECT 
            DATE(r.payment_date) as payment_date,
            COUNT(*) as transaction_count,
            SUM(r.amount_paid) as total_collected
        FROM receipts r
        WHERE DATE(r.payment_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        GROUP BY DATE(r.payment_date)
        ORDER BY payment_date DESC
    ");
    $stmt->execute();
    $daily_collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            -- Today
            COALESCE(SUM(CASE WHEN DATE(r.payment_date) = CURDATE() THEN r.amount_paid END), 0) as today_collections,
            COUNT(CASE WHEN DATE(r.payment_date) = CURDATE() THEN 1 END) as today_transactions,
            
            -- This week
            COALESCE(SUM(CASE WHEN DATE(r.payment_date) >= ? THEN r.amount_paid END), 0) as week_collections,
            COUNT(CASE WHEN DATE(r.payment_date) >= ? THEN 1 END) as week_transactions,
            
            -- This month
            COALESCE(SUM(CASE WHEN DATE(r.payment_date) >= ? THEN r.amount_paid END), 0) as month_collections,
            COUNT(CASE WHEN DATE(r.payment_date) >= ? THEN 1 END) as month_transactions,
            
            -- This year
            COALESCE(SUM(CASE WHEN DATE(r.payment_date) >= ? THEN r.amount_paid END), 0) as year_collections,
            COUNT(CASE WHEN DATE(r.payment_date) >= ? THEN 1 END) as year_transactions
        FROM receipts r
    ");
    $stmt->execute([$week_start, $week_start, $month_start, $month_start, $year_start, $year_start]);
    $summary_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Outstanding balances
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as unpaid_invoices,
            COALESCE(SUM(total_amount), 0) as total_outstanding,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), billing_date) <= 30 THEN 1 END) as current_30,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), billing_date) BETWEEN 31 AND 60 THEN 1 END) as aging_31_60,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), billing_date) BETWEEN 61 AND 90 THEN 1 END) as aging_61_90,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), billing_date) > 90 THEN 1 END) as aging_over_90,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), billing_date) <= 30 THEN total_amount END), 0) as amount_current_30,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), billing_date) BETWEEN 31 AND 60 THEN total_amount END), 0) as amount_31_60,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), billing_date) BETWEEN 61 AND 90 THEN total_amount END), 0) as amount_61_90,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), billing_date) > 90 THEN total_amount END), 0) as amount_over_90
        FROM billing 
        WHERE payment_status = 'unpaid'
    ");
    $outstanding_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Revenue by service
    $stmt = $pdo->query("
        SELECT 
            si.service_name,
            si.category,
            COUNT(bi.billing_item_id) as service_count,
            SUM(bi.quantity * bi.unit_price) as total_revenue
        FROM billing_items bi
        JOIN service_items si ON bi.service_item_id = si.service_item_id
        JOIN billing b ON bi.billing_id = b.billing_id
        WHERE b.billing_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        GROUP BY bi.service_item_id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $service_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment methods analysis
    $stmt = $pdo->query("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(amount_paid) as total_amount
        FROM receipts 
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top patients by revenue
    $stmt = $pdo->query("
        SELECT 
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            COUNT(DISTINCT b.billing_id) as invoice_count,
            SUM(b.total_amount) as total_billed,
            SUM(COALESCE(r_sum.total_paid, 0)) as total_paid
        FROM patients p
        JOIN billing b ON p.patient_id = b.patient_id
        LEFT JOIN (
            SELECT billing_id, SUM(amount_paid) as total_paid 
            FROM receipts 
            GROUP BY billing_id
        ) r_sum ON b.billing_id = r_sum.billing_id
        WHERE b.billing_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        GROUP BY p.patient_id
        ORDER BY total_billed DESC
        LIMIT 10
    ");
    $top_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Billing reports error: " . $e->getMessage());
    $daily_collections = [];
    $summary_stats = [
        'today_collections' => 0, 'today_transactions' => 0,
        'week_collections' => 0, 'week_transactions' => 0,
        'month_collections' => 0, 'month_transactions' => 0,
        'year_collections' => 0, 'year_transactions' => 0
    ];
    $outstanding_stats = [
        'unpaid_invoices' => 0, 'total_outstanding' => 0,
        'current_30' => 0, 'aging_31_60' => 0, 'aging_61_90' => 0, 'aging_over_90' => 0,
        'amount_current_30' => 0, 'amount_31_60' => 0, 'amount_61_90' => 0, 'amount_over_90' => 0
    ];
    $service_revenue = [];
    $payment_methods = [];
    $top_patients = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Reports - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--accent-color);
        }
        
        .summary-card.today { --accent-color: #007bff; }
        .summary-card.week { --accent-color: #28a745; }
        .summary-card.month { --accent-color: #ffc107; }
        .summary-card.year { --accent-color: #dc3545; }
        
        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--accent-color);
        }
        
        .summary-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .summary-sublabel {
            color: #999;
            font-size: 0.8rem;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .report-panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .panel-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-title {
            margin: 0;
            color: #333;
        }
        
        .panel-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .panel-content {
            padding: 1.5rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
            margin: 0.25rem 0;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .progress-fill.current { background: #28a745; }
        .progress-fill.aging-30 { background: #ffc107; }
        .progress-fill.aging-60 { background: #fd7e14; }
        .progress-fill.aging-90 { background: #dc3545; }
        
        .aging-breakdown {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .aging-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .aging-value {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .aging-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .metric-comparison {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .metric-name {
            font-weight: 500;
        }
        
        .metric-value {
            font-weight: bold;
            color: #28a745;
        }
        
        .export-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .export-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .export-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        
        .export-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #007bff;
        }
        
        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .aging-breakdown {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .export-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media print {
            .btn, .panel-actions, .export-section {
                display: none !important;
            }
            
            .reports-container {
                max-width: none;
            }
            
            .report-panel {
                break-inside: avoid;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="homepage">
    <div style="margin-left: 260px; padding: 20px; min-height: 100vh; background-color: #f5f5f5;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <h1 style="margin: 0; font-size: 1.8rem; font-weight: 600;"><i class="fas fa-chart-line"></i> Billing Reports</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 1rem;"><?php echo $employee_role === 'admin' ? 'Comprehensive financial reports and revenue analytics' : 'Generate billing reports and view financial summaries'; ?></p>
        </div>
        
        <div class="reports-container">
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
            
            <!-- Summary Statistics -->
            <div class="summary-grid">
                <div class="summary-card today">
                    <div class="summary-value"><?php echo number_format($summary_stats['today_collections'], 2); ?></div>
                    <div class="summary-label">Today's Collections</div>
                    <div class="summary-sublabel"><?php echo $summary_stats['today_transactions']; ?> transactions</div>
                </div>
                
                <div class="summary-card week">
                    <div class="summary-value"><?php echo number_format($summary_stats['week_collections'], 2); ?></div>
                    <div class="summary-label">This Week</div>
                    <div class="summary-sublabel"><?php echo $summary_stats['week_transactions']; ?> transactions</div>
                </div>
                
                <div class="summary-card month">
                    <div class="summary-value"><?php echo number_format($summary_stats['month_collections'], 2); ?></div>
                    <div class="summary-label">This Month</div>
                    <div class="summary-sublabel"><?php echo $summary_stats['month_transactions']; ?> transactions</div>
                </div>
                
                <div class="summary-card year">
                    <div class="summary-value"><?php echo number_format($summary_stats['year_collections'], 2); ?></div>
                    <div class="summary-label">This Year</div>
                    <div class="summary-sublabel"><?php echo $summary_stats['year_transactions']; ?> transactions</div>
                </div>
            </div>
            
            <!-- Export Section -->
            <div class="export-section">
                <h3><i class="fas fa-download"></i> Export Reports</h3>
                <div class="export-grid">
                    <div class="export-card" onclick="generateReport('daily_collections')">
                        <i class="fas fa-chart-line export-icon"></i>
                        <h5>Daily Collections</h5>
                        <p>Last 30 days revenue</p>
                    </div>
                    
                    <div class="export-card" onclick="generateReport('outstanding_balances')">
                        <i class="fas fa-exclamation-triangle export-icon"></i>
                        <h5>Outstanding Balances</h5>
                        <p>Aging analysis report</p>
                    </div>
                    
                    <div class="export-card" onclick="generateReport('service_revenue')">
                        <i class="fas fa-chart-pie export-icon"></i>
                        <h5>Service Revenue</h5>
                        <p>Revenue by service type</p>
                    </div>
                    
                    <div class="export-card" onclick="generateReport('payment_methods')">
                        <i class="fas fa-credit-card export-icon"></i>
                        <h5>Payment Methods</h5>
                        <p>Payment analysis</p>
                    </div>
                </div>
            </div>
            
            <!-- Main Reports Grid -->
            <div class="reports-grid">
                <!-- Daily Collections Chart -->
                <div class="report-panel">
                    <div class="panel-header">
                        <h4 class="panel-title">Daily Collections (Last 30 Days)</h4>
                        <div class="panel-actions">
                            <button class="btn btn-outline" onclick="refreshChart('collections')">
                                <i class="fas fa-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="panel-content">
                        <div class="chart-container">
                            <canvas id="collectionsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Outstanding Balances -->
                <div class="report-panel">
                    <div class="panel-header">
                        <h4 class="panel-title">Outstanding Balances</h4>
                        <div class="panel-actions">
                            <button class="btn btn-primary" onclick="exportAgingReport()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="panel-content">
                        <div class="aging-breakdown">
                            <div class="aging-item">
                                <div class="aging-value"><?php echo $outstanding_stats['current_30']; ?></div>
                                <div class="aging-label">0-30 Days</div>
                                <div style="font-size: 0.8rem; color: #28a745;"><?php echo number_format($outstanding_stats['amount_current_30'], 2); ?></div>
                            </div>
                            <div class="aging-item">
                                <div class="aging-value"><?php echo $outstanding_stats['aging_31_60']; ?></div>
                                <div class="aging-label">31-60 Days</div>
                                <div style="font-size: 0.8rem; color: #ffc107;"><?php echo number_format($outstanding_stats['amount_31_60'], 2); ?></div>
                            </div>
                            <div class="aging-item">
                                <div class="aging-value"><?php echo $outstanding_stats['aging_61_90']; ?></div>
                                <div class="aging-label">61-90 Days</div>
                                <div style="font-size: 0.8rem; color: #fd7e14;"><?php echo number_format($outstanding_stats['amount_61_90'], 2); ?></div>
                            </div>
                            <div class="aging-item">
                                <div class="aging-value"><?php echo $outstanding_stats['aging_over_90']; ?></div>
                                <div class="aging-label">Over 90 Days</div>
                                <div style="font-size: 0.8rem; color: #dc3545;"><?php echo number_format($outstanding_stats['amount_over_90'], 2); ?></div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #dc3545;">
                                <?php echo number_format($outstanding_stats['total_outstanding'], 2); ?>
                            </div>
                            <div style="color: #666;">Total Outstanding</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Service Revenue & Payment Methods -->
            <div class="reports-grid">
                <!-- Top Services by Revenue -->
                <div class="report-panel">
                    <div class="panel-header">
                        <h4 class="panel-title">Top Services by Revenue (30 Days)</h4>
                        <div class="panel-actions">
                            <button class="btn btn-outline" onclick="viewAllServices()">
                                <i class="fas fa-list"></i> View All
                            </button>
                        </div>
                    </div>
                    <div class="panel-content">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Count</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($service_revenue)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; color: #666;">No data available</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($service_revenue as $service): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                                    <?php if ($service['category']): ?>
                                                        <br><small style="color: #666;"><?php echo htmlspecialchars($service['category']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($service['service_count']); ?></td>
                                                <td><?php echo number_format($service['total_revenue'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Methods Analysis -->
                <div class="report-panel">
                    <div class="panel-header">
                        <h4 class="panel-title">Payment Methods (30 Days)</h4>
                        <div class="panel-actions">
                            <button class="btn btn-outline" onclick="refreshChart('payments')">
                                <i class="fas fa-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="panel-content">
                        <div class="chart-container">
                            <canvas id="paymentMethodsChart"></canvas>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <?php foreach ($payment_methods as $method): ?>
                                <div class="metric-comparison">
                                    <div class="metric-name"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></div>
                                    <div class="metric-value"><?php echo number_format($method['total_amount'], 2); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Patients -->
            <div class="report-panel">
                <div class="panel-header">
                    <h4 class="panel-title">Top Patients by Revenue (30 Days)</h4>
                    <div class="panel-actions">
                        <button class="btn btn-success" onclick="exportPatientReport()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="panel-content">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Invoices</th>
                                    <th>Total Billed</th>
                                    <th>Total Paid</th>
                                    <th>Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_patients)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #666;">No data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_patients as $patient): ?>
                                        <?php $outstanding = $patient['total_billed'] - $patient['total_paid']; ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                                <br><small style="color: #666;">ID: <?php echo $patient['patient_id']; ?></small>
                                            </td>
                                            <td><?php echo number_format($patient['invoice_count']); ?></td>
                                            <td><?php echo number_format($patient['total_billed'], 2); ?></td>
                                            <td><?php echo number_format($patient['total_paid'], 2); ?></td>
                                            <td style="color: <?php echo $outstanding > 0 ? '#dc3545' : '#28a745'; ?>;">
                                                <?php echo number_format($outstanding, 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <script>
        // Chart configurations
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
            },
        };
        
        // Daily Collections Chart
        const collectionsData = <?php echo json_encode(array_reverse($daily_collections)); ?>;
        const collectionsCtx = document.getElementById('collectionsChart').getContext('2d');
        
        new Chart(collectionsCtx, {
            type: 'line',
            data: {
                labels: collectionsData.map(item => formatDate(item.payment_date)),
                datasets: [{
                    label: 'Daily Collections',
                    data: collectionsData.map(item => parseFloat(item.total_collected)),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Collections: ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Payment Methods Chart
        const paymentData = <?php echo json_encode($payment_methods); ?>;
        const paymentCtx = document.getElementById('paymentMethodsChart').getContext('2d');
        
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentData.map(item => item.payment_method.replace('_', ' ').toUpperCase()),
                datasets: [{
                    data: paymentData.map(item => parseFloat(item.total_amount)),
                    backgroundColor: [
                        '#007bff',
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        
        // Utility functions
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PH', {
                month: 'short',
                day: 'numeric'
            });
        }
        
        async function generateReport(reportType) {
            try {
                const url = `../../../../api/billing/management/get_billing_reports.php?type=${reportType}&format=pdf`;
                const link = document.createElement('a');
                link.href = url;
                link.download = `${reportType}_${new Date().toISOString().slice(0,10)}.pdf`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Export error:', error);
                alert('Failed to generate report. Please try again.');
            }
        }
        
        function exportAgingReport() {
            generateReport('aging_analysis');
        }
        
        function exportPatientReport() {
            generateReport('patient_revenue');
        }
        
        function refreshChart(chartType) {
            // Reload the page to refresh data
            window.location.reload();
        }
        
        function viewAllServices() {
            // Navigate to a detailed services report
            window.open('../../../../api/billing/management/get_billing_reports.php?type=service_details&format=html', '_blank');
        }
        
        // Print functionality
        function printReport() {
            window.print();
        }
        
        // Auto-dismiss alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReport();
            }
        });
    </script>
</body>
</html>
