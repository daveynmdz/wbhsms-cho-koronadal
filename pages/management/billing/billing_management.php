<?php
// Billing Management Dashboard - Comprehensive Billing Interface
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in and has cashier/admin privileges
if (!is_employee_logged_in()) {
    header("Location: ../auth/employee_login.php");
    exit();
}

$employee_role = get_employee_session('role');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    header("Location: ../../dashboard.php?error=Access denied");
    exit();
}

$employee_id = get_employee_session('employee_id');
$employee_name = get_employee_session('first_name') . ' ' . get_employee_session('last_name');
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Get today's summary statistics
$today = date('Y-m-d');
try {
    // Today's collections
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as transactions_today,
            COALESCE(SUM(amount_paid), 0) as total_collected,
            COUNT(DISTINCT r.billing_id) as bills_paid
        FROM receipts r
        JOIN billing b ON r.billing_id = b.billing_id
        WHERE DATE(r.payment_date) = ?
    ");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pending bills
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count,
               COALESCE(SUM(total_amount), 0) as pending_amount
        FROM billing 
        WHERE payment_status = 'unpaid'
    ");
    $stmt->execute();
    $pending_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recent transactions
    $stmt = $pdo->prepare("
        SELECT 
            r.receipt_id,
            r.billing_id,
            r.amount_paid,
            r.payment_date,
            r.payment_method,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            b.total_amount
        FROM receipts r
        JOIN billing b ON r.billing_id = b.billing_id
        JOIN patients p ON b.patient_id = p.patient_id
        ORDER BY r.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Billing dashboard error: " . $e->getMessage());
    $today_stats = ['transactions_today' => 0, 'total_collected' => 0, 'bills_paid' => 0];
    $pending_stats = ['pending_count' => 0, 'pending_amount' => 0];
    $recent_transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        .billing-management {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .billing-management {
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        .action-card.create .action-icon {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }

        .action-card.search .action-icon {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }

        .action-card.reports .action-icon {
            background: linear-gradient(135deg, #6f42c1, #563d7c);
        }

        .action-card.print .action-icon {
            background: linear-gradient(135deg, #fd7e14, #e55a00);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 0.8rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 3px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.collections {
            border-left-color: #28a745;
        }

        .stat-card.transactions {
            border-left-color: #007bff;
        }

        .stat-card.pending {
            border-left-color: #dc3545;
        }

        .stat-icon {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 0.6rem;
        }

        .stat-content h3 {
            margin: 0 0 0.2rem 0;
            font-size: 0.85rem;
            color: #495057;
            font-weight: 600;
        }

        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0077b6;
            margin: 0.2rem 0;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.75rem;
            margin: 0;
        }

        .stat-card.collections .stat-value {
            color: #28a745;
        }

        .stat-card.transactions .stat-value {
            color: #007bff;
        }

        .stat-card.pending .stat-value {
            color: #dc3545;
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

        .search-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        #searchResults {
            margin-top: 1rem;
        }

        .search-result-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .search-result-item:hover {
            background: #e9ecef;
            border-color: #007bff;
        }

        .result-info h4 {
            margin: 0 0 0.5rem 0;
            color: #0077b6;
            font-weight: 600;
        }

        .result-info p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }

        .result-actions {
            display: flex;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-unpaid {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-exempted {
            background: #cce5ff;
            color: #004085;
        }

        .transaction-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
            transition: all 0.2s ease;
        }

        .transaction-item:hover {
            background-color: #f8f9fa;
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: 8px;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            color: #0077b6;
            font-weight: 600;
        }

        .transaction-info p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
        }

        .transaction-amount {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1rem;
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
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                grid-template-columns: 1fr;
            }

            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .result-actions {
                flex-direction: column;
                gap: 0.25rem;
            }

            .search-result-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'billing';
    // Include appropriate sidebar based on user role
    if ($employee_role === 'admin') {
        include '../../../includes/sidebar_admin.php';
    } else {
        include '../../../includes/sidebar_cashier.php';
    }
    ?>

    <section class="billing-management">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <?php if ($employee_role === 'admin'): ?>
                <a href="../admin/dashboard.php"><i class="fas fa-home"></i> Admin Dashboard</a>
            <?php else: ?>
                <a href="../cashier/dashboard.php"><i class="fas fa-home"></i> Cashier Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">Billing Management</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-cash-register" style="margin-right: 0.5rem;"></i>Billing Management</h1>
            <div class="action-buttons">
                <a href="create_invoice.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Invoice
                </a>
                <a href="billing_reports.php" class="btn btn-secondary">
                    <i class="fas fa-chart-bar"></i> Reports
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
                <h2 class="section-title">Daily Overview</h2>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card collections">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Today's Collections</h3>
                        <div class="stat-value">₱<?php echo number_format($today_stats['total_collected'], 2); ?></div>
                        <div class="stat-label">collected today</div>
                    </div>
                </div>

                <div class="stat-card transactions">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #0056b3);">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Transactions Today</h3>
                        <div class="stat-value"><?php echo $today_stats['transactions_today']; ?></div>
                        <div class="stat-label">transactions</div>
                    </div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Bills</h3>
                        <div class="stat-value"><?php echo $pending_stats['pending_count']; ?></div>
                        <div class="stat-label">unpaid invoices</div>
                    </div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Outstanding Amount</h3>
                        <div class="stat-value">₱<?php echo number_format($pending_stats['pending_amount'], 2); ?></div>
                        <div class="stat-label">total outstanding</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="create_invoice.php" class="action-card create">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="action-content">
                    <h3>Create Invoice</h3>
                    <p>New patient billing</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </a>

            <a href="process_payment.php" class="action-card search">
                <div class="action-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="action-content">
                    <h3>Process Payment</h3>
                    <p>Accept payments</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </a>

            <a href="invoice_search.php" class="action-card search">
                <div class="action-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="action-content">
                    <h3>Search Invoices</h3>
                    <p>Find & manage bills</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </a>

            <a href="billing_reports.php" class="action-card reports">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-content">
                    <h3>Reports</h3>
                    <p>Financial analytics</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </a>

            <a href="print_receipt.php" class="action-card print">
                <div class="action-icon">
                    <i class="fas fa-print"></i>
                </div>
                <div class="action-content">
                    <h3>Print Receipt</h3>
                    <p>Reprint receipts</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </a>
        </div>

        <!-- Quick Patient Search -->
        <div class="search-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-user-search"></i>
                </div>
                <h3 class="section-title">Quick Patient Lookup</h3>
            </div>
            <div class="search-form">
                <div class="form-group">
                    <label for="patientSearch">Patient Name or ID</label>
                    <input type="text" id="patientSearch" class="form-control" placeholder="Enter patient name or ID...">
                </div>
                <div class="form-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="">All Status</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="exempted">Exempted</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" onclick="searchPatients()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
            <div id="searchResults"></div>
        </div>

        <!-- Recent Transactions -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="section-title">Recent Transactions</h3>
            </div>
            <div class="transaction-list">
                <?php if (empty($recent_transactions)): ?>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <p>No recent transactions found.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <h4><?php echo htmlspecialchars($transaction['patient_name']); ?></h4>
                                <p>
                                    Receipt #<?php echo str_pad($transaction['receipt_id'], 6, '0', STR_PAD_LEFT); ?>
                                    <?php echo date('M j, Y g:i A', strtotime($transaction['payment_date'])); ?>
                                    <?php echo ucfirst($transaction['payment_method']); ?>
                                </p>
                            </div>
                            <div class="transaction-amount">
                                ₱<?php echo number_format($transaction['amount_paid'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        let searchTimeout;

        function searchPatients() {
            const searchTerm = document.getElementById('patientSearch').value.trim();
            const statusFilter = document.getElementById('statusFilter').value;

            if (searchTerm.length < 2 && !statusFilter) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }

            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Set new timeout for search
            searchTimeout = setTimeout(() => {
                performSearch(searchTerm, statusFilter);
            }, 300);
        }

        async function performSearch(searchTerm, statusFilter) {
            try {
                const params = new URLSearchParams();
                if (searchTerm) params.append('search', searchTerm);
                if (statusFilter) params.append('status', statusFilter);
                params.append('limit', '20');

                const response = await fetch(`../../../../api/billing/management/search_invoices.php?${params}`);
                const result = await response.json();

                const resultsContainer = document.getElementById('searchResults');

                if (result.success && result.data.length > 0) {
                    resultsContainer.innerHTML = result.data.map(invoice => `
                        <div class="search-result-item">
                            <div class="result-info">
                                <h4>${invoice.patient_name}</h4>
                                <p>
                                    Invoice #${String(invoice.billing_id).padStart(6, '0')}  
                                    ${formatDate(invoice.billing_date)}  
                                    <span class="status-badge status-${invoice.payment_status}">
                                        ${invoice.payment_status.toUpperCase()}
                                    </span>
                                </p>
                                <p><strong>Amount:</strong> ${Number(invoice.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                            </div>
                            <div class="result-actions">
                                ${invoice.payment_status === 'unpaid' ? `
                                    <a href="process_payment.php?billing_id=${invoice.billing_id}" class="btn btn-success">
                                        <i class="fas fa-credit-card"></i> Pay
                                    </a>
                                ` : ''}
                                <a href="../../../../pages/patient/billing/invoice_details.php?billing_id=${invoice.billing_id}" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                ${invoice.has_receipt ? `
                                    <a href="print_receipt.php?billing_id=${invoice.billing_id}" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-print"></i> Receipt
                                    </a>
                                ` : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    resultsContainer.innerHTML = `
                        <div class="search-result-item">
                            <div class="result-info">
                                <p>No results found for "${searchTerm}"</p>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('searchResults').innerHTML = `
                    <div class="search-result-item">
                        <div class="result-info">
                            <p style="color: #dc3545;">Search failed. Please try again.</p>
                        </div>
                    </div>
                `;
            }
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Real-time search as user types
        document.getElementById('patientSearch').addEventListener('input', searchPatients);
        document.getElementById('statusFilter').addEventListener('change', searchPatients);

        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>

</html>