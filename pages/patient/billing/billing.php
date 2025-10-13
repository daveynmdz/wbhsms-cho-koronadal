<?php
// Start output buffering immediately to prevent any header issues
ob_start();

// Clean any potential output that might have been sent
if (ob_get_length()) {
    ob_clean();
}

// Set error reporting for debugging but don't display errors in production
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Never show errors to users in production
ini_set('log_errors', '1');      // Log errors for debugging

// Patient Billing Dashboard - Professional Healthcare Billing Interface
$root_path = dirname(dirname(dirname(__DIR__)));

// Load configuration first
require_once $root_path . '/config/env.php';

// Load database connection
require_once $root_path . '/config/db.php';

// Then load session management
require_once $root_path . '/config/session/patient_session.php';

// Ensure session is properly started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, redirect to login
if (!is_patient_logged_in()) {
    ob_clean(); // Clear output buffer before redirect
    header("Location: ../auth/patient_login.php");
    exit();
}

$patient_id = get_patient_session('patient_id');
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Fetch patient information
$patient_info = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, b.barangay_name
        FROM patients p
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate priority level
    if ($patient_info) {
        $patient_info['priority_level'] = ($patient_info['isPWD'] || $patient_info['isSenior']) ? 1 : 2;
        $patient_info['priority_description'] = ($patient_info['priority_level'] == 1) ? 'Priority Patient' : 'Regular Patient';
    }
} catch (Exception $e) {
    $error = "Failed to fetch patient information: " . $e->getMessage();
}

// Fetch billing summary data
$billing_summary = [];
$recent_bills = [];
$unpaid_bills = [];
try {
    // Get billing summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_count,
            COUNT(CASE WHEN payment_status = 'paid' AND YEAR(billing_date) = YEAR(CURDATE()) THEN 1 END) as paid_count,
            COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN total_amount END), 0) as total_outstanding,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' AND YEAR(billing_date) = YEAR(CURDATE()) THEN total_amount END), 0) as paid_this_year
        FROM billing 
        WHERE patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $billing_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent bills (limit to 10 for overview)
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            COUNT(bi.billing_item_id) as item_count,
            COALESCE(r_sum.total_paid, 0) as total_paid,
            (b.total_amount - COALESCE(r_sum.total_paid, 0)) as balance_due,
            CASE WHEN r_sum.total_paid >= b.total_amount THEN 1 ELSE 0 END as has_receipt
        FROM billing b
        LEFT JOIN billing_items bi ON b.billing_id = bi.billing_id
        LEFT JOIN (
            SELECT billing_id, SUM(amount_paid) as total_paid 
            FROM receipts 
            GROUP BY billing_id
        ) r_sum ON b.billing_id = r_sum.billing_id
        WHERE b.patient_id = ?
        GROUP BY b.billing_id
        ORDER BY b.billing_date DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unpaid bills separately
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            COUNT(bi.billing_item_id) as item_count,
            COALESCE(r_sum.total_paid, 0) as total_paid,
            (b.total_amount - COALESCE(r_sum.total_paid, 0)) as balance_due
        FROM billing b
        LEFT JOIN billing_items bi ON b.billing_id = bi.billing_id
        LEFT JOIN (
            SELECT billing_id, SUM(amount_paid) as total_paid 
            FROM receipts 
            GROUP BY billing_id
        ) r_sum ON b.billing_id = r_sum.billing_id
        WHERE b.patient_id = ? AND b.payment_status = 'unpaid'
        GROUP BY b.billing_id
        ORDER BY b.billing_date DESC
        LIMIT 20
    ");
    $stmt->execute([$patient_id]);
    $unpaid_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Billing data error: " . $e->getMessage());
    $error = "Failed to fetch billing data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bills - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            padding: 12px 28px;
            text-align: center;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .btn-primary i {
            margin-right: 8px;
            font-size: 18px;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #16a085, #0f6b5c);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #0f6b5c, #0a4f44);
            transform: translateY(-2px);
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
            justify-content: flex-start;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-left: 5px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .stat-card.outstanding {
            border-left-color: #dc3545;
        }

        .stat-card.paid {
            border-left-color: #28a745;
        }

        .stat-card.total {
            border-left-color: #007bff;
        }

        .stat-icon {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-content h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            color: #495057;
            font-weight: 600;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #0077b6;
            margin: 0.5rem 0;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border: 2px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .filter-tab.active {
            background: #0077b6;
            color: white;
            border-color: #023e8a;
        }

        .filter-tab:hover {
            background: #e3f2fd;
            border-color: #0077b6;
        }

        .filter-tab.active:hover {
            background: #023e8a;
        }

        .search-filters {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 2px rgba(0, 119, 182, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-filter {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-filter-primary {
            background: #0077b6;
            color: white;
        }

        .btn-filter-primary:hover {
            background: #023e8a;
        }

        .btn-filter-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-filter-secondary:hover {
            background: #5a6268;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .bill-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .bill-table thead {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .bill-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border: none;
        }

        .bill-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }

        .bill-table tbody tr {
            transition: all 0.2s ease;
        }

        .bill-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .bill-table tbody tr:last-child td {
            border-bottom: none;
        }

        .bill-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .bill-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .bill-card.paid {
            border-left: 4px solid #28a745;
        }

        .bill-card.unpaid {
            border-left: 4px solid #dc3545;
        }

        .bill-card.partial {
            border-left: 4px solid #ffc107;
        }

        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .bill-info h4 {
            margin: 0;
            color: #0077b6;
            font-weight: 600;
        }

        .bill-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0.25rem 0 0 0;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }

        .status-partial {
            background: #fff3cd;
            color: #856404;
        }

        .bill-content {
            padding: 1.5rem;
        }

        .bill-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0077b6;
            margin-bottom: 0.5rem;
        }

        .bill-details {
            color: #6c757d;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .bill-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid;
        }

        .btn-outline-primary {
            border-color: #0077b6;
            color: #0077b6;
        }

        .btn-outline-primary:hover {
            background: #0077b6;
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6c757d;
            margin: 0;
        }

        .loading-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .loading-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons {
                width: 100%;
                justify-content: flex-start;
            }

            .btn .hide-on-mobile {
                display: none;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
            }

            .btn-filter {
                flex: 1;
            }

            .table-container {
                overflow-x: auto;
            }

            .bill-table {
                min-width: 600px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        /* Alert Styles */
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
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'billing';
    include '../../../includes/sidebar_patient.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">My Bills</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-receipt" style="margin-right: 0.5rem;"></i>My Bills</h1>
            <div class="action-buttons">
                <a href="billing_history.php" class="btn btn-primary">
                    <i class="fas fa-history"></i>
                    <span class="hide-on-mobile">View Full History</span>
                </a>
                <button class="btn btn-secondary" onclick="showPaymentInfo()">
                    <i class="fas fa-info-circle"></i>
                    <span class="hide-on-mobile">Payment Info</span>
                </button>
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

        <!-- Billing Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card outstanding">
                <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-content">
                    <h3>Outstanding Bills</h3>
                    <p class="stat-number">₱<?php echo number_format($billing_summary['total_outstanding'] ?? 0, 2); ?></p>
                    <p class="stat-label"><?php echo $billing_summary['unpaid_count'] ?? 0; ?> unpaid bills</p>
                </div>
            </div>
            
            <div class="stat-card paid">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Paid This Year</h3>
                    <p class="stat-number">₱<?php echo number_format($billing_summary['paid_this_year'] ?? 0, 2); ?></p>
                    <p class="stat-label"><?php echo $billing_summary['paid_count'] ?? 0; ?> payments made</p>
                </div>
            </div>
            
            <div class="stat-card total">
                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #0056b3);">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Bills</h3>
                    <p class="stat-number"><?php echo $billing_summary['total_invoices'] ?? 0; ?></p>
                    <p class="stat-label">all time</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="billing_history.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="action-content">
                    <h3>View Full History</h3>
                    <p>See all billing records with filters</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </a>
            
            <div class="action-card" onclick="showPaymentInfo()">
                <div class="action-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="action-content">
                    <h3>Payment Information</h3>
                    <p>How to pay your bills</p>
                </div>
                <i class="fas fa-arrow-right action-arrow"></i>
            </div>
        </div>

        <!-- Unpaid Bills Section -->
        <?php if (!empty($unpaid_bills)): ?>
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="section-title">Unpaid Bills</h2>
            </div>

            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="unpaid-search">Search Bills</label>
                        <input type="text" id="unpaid-search" placeholder="Search by invoice number or amount..." 
                               onkeyup="handleSearchKeyPress(event, 'unpaid')" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label for="unpaid-date-from">Date From</label>
                        <input type="date" id="unpaid-date-from" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label for="unpaid-date-to">Date To</label>
                        <input type="date" id="unpaid-date-to" class="form-control">
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="btn-filter btn-filter-primary" onclick="filterUnpaidBills()">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <button type="button" class="btn-filter btn-filter-secondary" onclick="clearUnpaidFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="bill-table">
                    <thead>
                        <tr>
                            <th>Invoice Details</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Services</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="unpaid-bills-tbody">
                        <?php foreach ($unpaid_bills as $bill): ?>
                            <tr class="bill-row" data-invoice="<?php echo strtolower($bill['billing_id']); ?>" 
                                data-date="<?php echo $bill['billing_date']; ?>" 
                                data-amount="<?php echo $bill['total_amount']; ?>">
                                <td>
                                    <div class="bill-info">
                                        <strong style="color: #0077b6;">Invoice #<?php echo str_pad($bill['billing_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                        <br><small style="color: #6c757d;"><?php echo date('F j, Y', strtotime($bill['billing_date'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div style="color: #dc3545; font-weight: bold; font-size: 1.1rem;">
                                        ₱<?php echo number_format($bill['total_amount'], 2); ?>
                                    </div>
                                    <?php if ($bill['balance_due'] > 0 && $bill['balance_due'] < $bill['total_amount']): ?>
                                        <small style="color: #6c757d;">Balance: ₱<?php echo number_format($bill['balance_due'], 2); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $due_date = date('Y-m-d', strtotime($bill['billing_date'] . ' +30 days'));
                                    $is_overdue = $due_date < date('Y-m-d');
                                    ?>
                                    <div style="color: <?php echo $is_overdue ? '#dc3545' : '#495057'; ?>;">
                                        <?php echo date('M j, Y', strtotime($due_date)); ?>
                                    </div>
                                    <?php if ($is_overdue): ?>
                                        <small style="color: #dc3545; font-weight: bold;">OVERDUE</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo $bill['item_count']; ?> service<?php echo $bill['item_count'] != 1 ? 's' : ''; ?></div>
                                </td>
                                <td>
                                    <div class="bill-actions">
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewBillDetails(<?php echo $bill['billing_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Bills Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <h2 class="section-title">Recent Bills</h2>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="bill-search">Search Bills</label>
                        <input type="text" id="bill-search" placeholder="Search by invoice number or amount..." 
                               onkeyup="handleSearchKeyPress(event, 'recent')" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label for="bill-date-from">Date From</label>
                        <input type="date" id="bill-date-from" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label for="bill-date-to">Date To</label>
                        <input type="date" id="bill-date-to" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label for="bill-status-filter">Status</label>
                        <select id="bill-status-filter" class="form-control">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="btn-filter btn-filter-primary" onclick="filterRecentBills()">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <button type="button" class="btn-filter btn-filter-secondary" onclick="clearRecentFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterBills('all', this)">All Bills</div>
                <div class="filter-tab" onclick="filterBills('unpaid', this)">Unpaid</div>
                <div class="filter-tab" onclick="filterBills('paid', this)">Paid</div>
                <div class="filter-tab" onclick="filterBills('partial', this)">Partial</div>
            </div>

            <div id="billsContainer">
                <?php if (empty($recent_bills)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Bills Found</h3>
                        <p>You don't have any billing records yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_bills as $bill): ?>
                        <div class="bill-card <?php echo strtolower($bill['payment_status']); ?>" 
                             data-status="<?php echo $bill['payment_status']; ?>"
                             data-invoice="<?php echo strtolower($bill['billing_id']); ?>" 
                             data-date="<?php echo $bill['billing_date']; ?>" 
                             data-amount="<?php echo $bill['total_amount']; ?>">
                            <div class="bill-header">
                                <div class="bill-info">
                                    <h4>Invoice #<?php echo str_pad($bill['billing_id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                    <p class="bill-date"><?php echo date('F j, Y', strtotime($bill['billing_date'])); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo strtolower($bill['payment_status']); ?>">
                                    <?php echo strtoupper($bill['payment_status']); ?>
                                </span>
                            </div>
                            <div class="bill-content">
                                <div class="bill-amount">
                                    ₱<?php echo number_format($bill['total_amount'], 2); ?>
                                </div>
                                <div class="bill-details">
                                    <?php echo $bill['item_count']; ?> service<?php echo $bill['item_count'] != 1 ? 's' : ''; ?>
                                    <?php if ($bill['balance_due'] > 0): ?>
                                        • Balance: ₱<?php echo number_format($bill['balance_due'], 2); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="bill-actions">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewBillDetails(<?php echo $bill['billing_id']; ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <?php if ($bill['has_receipt']): ?>
                                        <button class="btn btn-primary btn-sm" onclick="downloadReceipt(<?php echo $bill['billing_id']; ?>)">
                                            <i class="fas fa-download"></i> Receipt
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        // Bill filtering functionality
        function filterBills(status, clickedElement) {
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Add active class to clicked tab
            if (clickedElement) {
                clickedElement.classList.add('active');
            }

            // Show/hide bills based on status
            const bills = document.querySelectorAll('.bill-card');
            let visibleCount = 0;

            bills.forEach(bill => {
                const billStatus = bill.getAttribute('data-status');
                if (status === 'all' || billStatus === status) {
                    bill.style.display = 'block';
                    visibleCount++;
                } else {
                    bill.style.display = 'none';
                }
            });

            // Show no results message if no bills match the filter
            if (visibleCount === 0 && bills.length > 0) {
                showNoResultsMessage();
            } else {
                hideNoResultsMessage();
            }
        }

        // Handle Enter key press in search fields
        function handleSearchKeyPress(event, type) {
            if (event.key === 'Enter') {
                if (type === 'recent') {
                    filterRecentBills();
                } else if (type === 'unpaid') {
                    filterUnpaidBills();
                }
            }
        }

        // Advanced filter functionality for recent bills
        function filterRecentBills() {
            const searchTerm = document.getElementById('bill-search').value.toLowerCase();
            const dateFrom = document.getElementById('bill-date-from').value;
            const dateTo = document.getElementById('bill-date-to').value;
            const statusFilter = document.getElementById('bill-status-filter').value;

            const bills = document.querySelectorAll('.bill-card');
            let visibleCount = 0;

            bills.forEach(bill => {
                const invoiceText = bill.getAttribute('data-invoice');
                const billDate = bill.getAttribute('data-date');
                const billAmount = bill.getAttribute('data-amount');
                const billStatus = bill.getAttribute('data-status');

                let shouldShow = true;

                // Text search
                if (searchTerm && !invoiceText.includes(searchTerm) && !billAmount.includes(searchTerm)) {
                    shouldShow = false;
                }

                // Date range filter
                if (dateFrom && billDate < dateFrom) {
                    shouldShow = false;
                }
                if (dateTo && billDate > dateTo) {
                    shouldShow = false;
                }

                // Status filter
                if (statusFilter && billStatus !== statusFilter) {
                    shouldShow = false;
                }

                bill.style.display = shouldShow ? 'block' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no bills match the filter
            if (visibleCount === 0 && bills.length > 0) {
                showNoResultsMessage();
            } else {
                hideNoResultsMessage();
            }

            // Clear tab selections when using search
            if (searchTerm || dateFrom || dateTo || statusFilter) {
                const tabs = document.querySelectorAll('.filter-tab');
                tabs.forEach(tab => {
                    tab.classList.remove('active');
                });
            }
        }

        // Advanced filter functionality for unpaid bills
        function filterUnpaidBills() {
            const searchTerm = document.getElementById('unpaid-search').value.toLowerCase();
            const dateFrom = document.getElementById('unpaid-date-from').value;
            const dateTo = document.getElementById('unpaid-date-to').value;

            const bills = document.querySelectorAll('#unpaid-bills-tbody .bill-row');
            let visibleCount = 0;

            bills.forEach(bill => {
                const invoiceText = bill.getAttribute('data-invoice');
                const billDate = bill.getAttribute('data-date');
                const billAmount = bill.getAttribute('data-amount');

                let shouldShow = true;

                // Text search
                if (searchTerm && !invoiceText.includes(searchTerm) && !billAmount.includes(searchTerm)) {
                    shouldShow = false;
                }

                // Date range filter
                if (dateFrom && billDate < dateFrom) {
                    shouldShow = false;
                }
                if (dateTo && billDate > dateTo) {
                    shouldShow = false;
                }

                bill.style.display = shouldShow ? 'table-row' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no bills match the filter
            if (visibleCount === 0 && bills.length > 0) {
                showNoResultsMessage();
            } else {
                hideNoResultsMessage();
            }
        }

        // Clear recent bills filters
        function clearRecentFilters() {
            document.getElementById('bill-search').value = '';
            document.getElementById('bill-date-from').value = '';
            document.getElementById('bill-date-to').value = '';
            document.getElementById('bill-status-filter').value = '';

            // Show all bills
            const bills = document.querySelectorAll('.bill-card');
            bills.forEach(bill => {
                bill.style.display = 'block';
            });

            // Reset filter tabs
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            const allTab = tabs[0];
            if (allTab) allTab.classList.add('active');

            hideNoResultsMessage();
        }

        // Clear unpaid bills filters
        function clearUnpaidFilters() {
            document.getElementById('unpaid-search').value = '';
            document.getElementById('unpaid-date-from').value = '';
            document.getElementById('unpaid-date-to').value = '';

            // Show all unpaid bills
            const bills = document.querySelectorAll('#unpaid-bills-tbody .bill-row');
            bills.forEach(bill => {
                bill.style.display = 'table-row';
            });

            hideNoResultsMessage();
        }

        // Show no results message
        function showNoResultsMessage() {
            hideNoResultsMessage(); // Remove existing message first
            
            const container = document.getElementById('billsContainer');
            const noResultsDiv = document.createElement('div');
            noResultsDiv.className = 'no-results-message empty-state';
            noResultsDiv.innerHTML = `
                <i class="fas fa-search"></i>
                <h3>No Bills Found</h3>
                <p>No bills match your current filters. Try adjusting your search criteria.</p>
            `;
            container.appendChild(noResultsDiv);
        }

        // Hide no results message
        function hideNoResultsMessage() {
            const noResultsMessage = document.querySelector('.no-results-message');
            if (noResultsMessage) {
                noResultsMessage.remove();
            }
        }

        function viewBillDetails(billingId) {
            window.open(`invoice_details.php?billing_id=${billingId}`, '_blank', 'width=800,height=600');
        }

        function downloadReceipt(billingId) {
            window.open(`/wbhsms-cho-koronadal-1/api/billing/patient/download_receipt.php?billing_id=${billingId}&format=html`, '_blank');
        }

        function showPaymentInfo() {
            alert('Payment Information:\n\nVisit CHO Koronadal to pay your bill:\n\nLocation: City Health Office, Koronadal City\nHours: Monday-Friday, 8:00 AM - 5:00 PM\nPhone: (083) 228-8045\n\nPayment Methods:\n• Cash (at cashier window)\n• Check (with valid ID)\n\nPlease bring your bill reference number when paying.');
        }

        // Auto-dismiss alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus first search input
            const firstSearchInput = document.getElementById('bill-search');
            if (firstSearchInput) {
                // Don't auto-focus on mobile to prevent keyboard popup
                if (window.innerWidth > 768) {
                    firstSearchInput.focus();
                }
            }
        });
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
