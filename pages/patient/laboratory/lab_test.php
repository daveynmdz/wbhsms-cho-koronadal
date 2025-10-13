<?php
// Include patient session configuration FIRST - before any output
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, redirect to login
if (!isset($_SESSION['patient_id'])) {
    header('Location: ../auth/patient_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Include automatic status updater
require_once $root_path . '/utils/automatic_status_updater.php';

$patient_id = $_SESSION['patient_id'];
$message = '';
$error = '';

// Run automatic status updates when page loads
try {
    $status_updater = new AutomaticStatusUpdater($conn);
    $update_result = $status_updater->runAllUpdates();
    
    // Optional: Show update message to user (you can remove this if you don't want to show it)
    if ($update_result['success'] && $update_result['total_updates'] > 0) {
        $message = "Status updates applied: " . $update_result['total_updates'] . " records updated automatically.";
    }
} catch (Exception $e) {
    // Log error but don't show to user to avoid confusion
    error_log("Failed to run automatic status updates: " . $e->getMessage());
}

// Fetch patient information
$patient_info = null;
try {
    $stmt = $conn->prepare("
        SELECT p.*, b.barangay_name
        FROM patients p
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_info = $result->fetch_assoc();

    // Calculate priority level
    if ($patient_info) {
        $patient_info['priority_level'] = ($patient_info['isPWD'] || $patient_info['isSenior']) ? 1 : 2;
        $patient_info['priority_description'] = ($patient_info['priority_level'] == 1) ? 'Priority Patient' : 'Regular Patient';
    }

    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch patient information: " . $e->getMessage();
}

// Fetch lab orders (limit to recent 50 for better overview)
$lab_orders = [];
$lab_results = [];
try {
    // Fetch pending/in-progress/cancelled lab orders
    $stmt = $conn->prepare("
        SELECT lo.*,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name,
               a.scheduled_date, a.scheduled_time,
               c.consultation_date
        FROM lab_orders lo
        LEFT JOIN employees e ON lo.ordered_by_employee_id = e.employee_id
        LEFT JOIN appointments a ON lo.appointment_id = a.appointment_id
        LEFT JOIN consultations c ON lo.consultation_id = c.consultation_id
        WHERE lo.patient_id = ? AND lo.status IN ('pending', 'in_progress', 'cancelled')
        ORDER BY lo.order_date DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lab_orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch completed lab results
    $stmt = $conn->prepare("
        SELECT lo.*,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name,
               a.scheduled_date, a.scheduled_time,
               c.consultation_date
        FROM lab_orders lo
        LEFT JOIN employees e ON lo.ordered_by_employee_id = e.employee_id
        LEFT JOIN appointments a ON lo.appointment_id = a.appointment_id
        LEFT JOIN consultations c ON lo.consultation_id = c.consultation_id
        WHERE lo.patient_id = ? AND lo.status = 'completed'
        ORDER BY lo.result_date DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lab_results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch lab orders: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Lab Tests - CHO Koronadal</title>
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

        .prescription-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .prescription-table thead {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .prescription-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border: none;
        }

        .prescription-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }

        .prescription-table tbody tr {
            transition: all 0.2s ease;
        }

        .prescription-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .prescription-table tbody tr:last-child td {
            border-bottom: none;
        }

        .date-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .date-info strong {
            color: #0077b6;
            font-weight: 600;
        }

        .date-info small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .doctor-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .doctor-info strong {
            color: #495057;
            font-weight: 600;
        }

        .doctor-info small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .text-muted {
            color: #6c757d !important;
            font-style: italic;
        }

        .medication-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .medication-info strong {
            color: #0077b6;
            font-weight: 600;
        }

        .medication-info small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .action-buttons-cell {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .empty-row td {
            padding: 3rem 1rem;
            text-align: center;
            border: none;
        }

        .empty-row .empty-state {
            color: #6c757d;
        }

        .empty-row .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-row .empty-state h3 {
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .empty-row .empty-state p {
            color: #6c757d;
            margin: 0;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #cce5ff;
            color: #004085;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Result type badges */
        .result-type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .result-type-badge.file {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .result-type-badge.text {
            background-color: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #ce93d8;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
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

        .btn-outline-success {
            border-color: #00b894;
            color: #00b894;
        }

        .btn-outline-success:hover {
            background: #00b894;
            color: white;
        }

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                justify-content: center;
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

            .prescription-table {
                min-width: 600px;
            }

            .action-buttons-cell {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'lab_tests';
    include '../../../includes/sidebar_patient.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span style="color: #0077b6; font-weight: 600;">My Lab Tests</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-flask" style="margin-right: 0.5rem;"></i>My Lab Tests</h1>
            <div class="action-buttons">
                <a href="../appointment/appointments.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-check"></i>
                    <span class="hide-on-mobile">View Appointments</span>
                </a>
                <button class="btn btn-primary" onclick="downloadLabHistory()">
                    <i class="fas fa-download"></i>
                    <span class="hide-on-mobile">Download History</span>
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Lab Orders Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-vial"></i>
                </div>
                <h2 class="section-title">Lab Orders Status</h2>
                <div style="margin-left: auto; color: #6c757d; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> Pending & In-Progress Orders
                </div>
            </div>

            <!-- Search and Filter Section for Orders -->
            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="order-search">Search Lab Orders</label>
                        <input type="text" id="order-search" placeholder="Search by test type, doctor, or order ID..." 
                               onkeypress="handleSearchKeyPress(event, 'order')">
                    </div>
                    <div class="filter-group">
                        <label for="order-date-from">Date From</label>
                        <input type="date" id="order-date-from">
                    </div>
                    <div class="filter-group">
                        <label for="order-date-to">Date To</label>
                        <input type="date" id="order-date-to">
                    </div>
                    <div class="filter-group">
                        <label for="order-status-filter">Status</label>
                        <select id="order-status-filter">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="filter-actions">
                            <button type="button" class="btn-filter btn-filter-primary" onclick="filterOrdersBySearch()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button type="button" class="btn-filter btn-filter-secondary" onclick="clearOrderFilters()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs for Orders -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterOrders('all', this)">
                    <i class="fas fa-list"></i> All Orders
                </div>
                <div class="filter-tab" onclick="filterOrders('pending', this)">
                    <i class="fas fa-clock"></i> Pending
                </div>
                <div class="filter-tab" onclick="filterOrders('in_progress', this)">
                    <i class="fas fa-spinner"></i> In Progress
                </div>
                <div class="filter-tab" onclick="filterOrders('cancelled', this)">
                    <i class="fas fa-times-circle"></i> Cancelled
                </div>
            </div>

            <!-- Lab Orders Table -->
            <div class="table-container">
                <table class="prescription-table" id="orders-table">
                    <thead>
                        <tr>
                            <th>Order Date</th>
                            <th>Test Type</th>
                            <th>Ordered By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody">
                        <?php if (empty($lab_orders)): ?>
                            <tr class="empty-row">
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-vial"></i>
                                    <h3>No Lab Orders Found</h3>
                                    <p>You don't have any pending lab orders. Lab orders will appear here when doctors order tests for you.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lab_orders as $order): ?>
                                <tr class="order-row" data-status="<?php echo htmlspecialchars($order['status']); ?>" data-order-date="<?php echo htmlspecialchars($order['order_date']); ?>">
                                    <td>
                                        <div class="date-info">
                                            <strong><?php echo date('M j, Y', strtotime($order['order_date'])); ?></strong>
                                            <small><?php echo date('g:i A', strtotime($order['order_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="test-info">
                                            <strong><?php echo htmlspecialchars($order['test_type']); ?></strong>
                                            <?php if (!empty($order['remarks'])): ?>
                                                <small><?php echo htmlspecialchars(substr($order['remarks'], 0, 50)) . (strlen($order['remarks']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="doctor-info">
                                            <?php if (!empty($order['doctor_first_name'])): ?>
                                                <strong>Dr. <?php echo htmlspecialchars($order['doctor_first_name'] . ' ' . $order['doctor_last_name']); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">Lab Direct Order</span>
                                            <?php endif; ?>
                                            <?php if (!empty($order['consultation_date'])): ?>
                                                <small>Consultation: <?php echo date('M j, Y', strtotime($order['consultation_date'])); ?></small>
                                            <?php elseif (!empty($order['appointment_date'])): ?>
                                                <small>Appointment: <?php echo date('M j, Y', strtotime($order['appointment_date'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars(str_replace('_', '-', $order['status'])); ?>">
                                            <?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $order['status']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons-cell">
                                            <button type="button" class="btn btn-outline btn-outline-primary btn-sm" onclick="viewOrderDetails(<?php echo $order['lab_order_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lab Results Section -->
        <div class="section-container">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-file-medical-alt"></i>
                </div>
                <h2 class="section-title">Lab Results</h2>
                <div style="margin-left: auto; color: #6c757d; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> Completed Test Results
                </div>
            </div>

            <!-- Search and Filter Section for Results -->
            <div class="search-filters">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="result-search">Search Lab Results</label>
                        <input type="text" id="result-search" placeholder="Search by test type, doctor, or result ID..." 
                               onkeypress="handleSearchKeyPress(event, 'result')">
                    </div>
                    <div class="filter-group">
                        <label for="result-date-from">Date From</label>
                        <input type="date" id="result-date-from">
                    </div>
                    <div class="filter-group">
                        <label for="result-date-to">Date To</label>
                        <input type="date" id="result-date-to">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="filter-actions">
                            <button type="button" class="btn-filter btn-filter-primary" onclick="filterResultsBySearch()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button type="button" class="btn-filter btn-filter-secondary" onclick="clearResultFilters()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lab Results Table -->
            <div class="table-container">
                <table class="prescription-table" id="results-table">
                    <thead>
                        <tr>
                            <th>Result Date</th>
                            <th>Test Type</th>
                            <th>Ordered By</th>
                            <th>Result Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="results-tbody">
                        <?php if (empty($lab_results)): ?>
                            <tr class="empty-row">
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-file-medical-alt"></i>
                                    <h3>No Lab Results Found</h3>
                                    <p>You don't have any completed lab results yet. Results will appear here when tests are completed.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lab_results as $result): ?>
                                <tr class="result-row" data-result-date="<?php echo htmlspecialchars($result['result_date']); ?>">
                                    <td>
                                        <div class="date-info">
                                            <strong><?php echo date('M j, Y', strtotime($result['result_date'])); ?></strong>
                                            <small><?php echo date('g:i A', strtotime($result['result_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="test-info">
                                            <strong><?php echo htmlspecialchars($result['test_type']); ?></strong>
                                            <small>Order: <?php echo date('M j, Y', strtotime($result['order_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="doctor-info">
                                            <?php if (!empty($result['doctor_first_name'])): ?>
                                                <strong>Dr. <?php echo htmlspecialchars($result['doctor_first_name'] . ' ' . $result['doctor_last_name']); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">Lab Direct Order</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $isFile = !empty($result['result']) && (
                                            strpos($result['result'], '.pdf') !== false || 
                                            strpos($result['result'], '.jpg') !== false || 
                                            strpos($result['result'], '.png') !== false || 
                                            strpos($result['result'], '.jpeg') !== false ||
                                            strpos($result['result'], '.gif') !== false ||
                                            strpos($result['result'], '.doc') !== false ||
                                            strpos($result['result'], '.docx') !== false ||
                                            strpos($result['result'], 'uploads/') !== false ||
                                            preg_match('/\.(pdf|jpe?g|png|gif|doc|docx)$/i', $result['result'])
                                        );
                                        ?>
                                        <?php if ($isFile): ?>
                                            <span class="result-type-badge file">
                                                <i class="fas fa-file"></i> File
                                            </span>
                                        <?php else: ?>
                                            <span class="result-type-badge text">
                                                <i class="fas fa-align-left"></i> Text
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons-cell">
                                            <?php if ($isFile): ?>
                                                <button type="button" class="btn btn-outline btn-outline-primary btn-sm" onclick="viewResultFile('<?php echo htmlspecialchars($result['result']); ?>')">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button type="button" class="btn btn-outline btn-outline-success btn-sm" onclick="downloadResultFile('<?php echo htmlspecialchars($result['result']); ?>', <?php echo $result['lab_order_id']; ?>)">
                                                    <i class="fas fa-download"></i> Download
                                                </button>
                                                <button type="button" class="btn btn-outline btn-outline-secondary btn-sm" onclick="printResultFile('<?php echo htmlspecialchars($result['result']); ?>')">
                                                    <i class="fas fa-print"></i> Print
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline btn-outline-primary btn-sm" onclick="viewResultDetails(<?php echo $result['lab_order_id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button type="button" class="btn btn-outline btn-outline-secondary btn-sm" onclick="printResult(<?php echo $result['lab_order_id']; ?>)">
                                                    <i class="fas fa-print"></i> Print
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Lab Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-vial"></i> Lab Order Details</h2>
                <span class="close" onclick="closeOrderModal()">&times;</span>
            </div>
            <div class="modal-body" id="orderModalBody">
                <!-- Content will be loaded dynamically -->
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading order details...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Lab Result Details Modal -->
    <div id="resultModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-medical-alt"></i> Lab Result Details</h2>
                <span class="close" onclick="closeResultModal()">&times;</span>
            </div>
            <div class="modal-body" id="resultModalBody">
                <!-- Content will be loaded dynamically -->
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading result details...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeResultModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="printResultBtn" onclick="printCurrentResult()">
                    <i class="fas fa-print"></i> Print Result
                </button>
            </div>
        </div>
    </div>

    <script>
        // Lab order and result variables
        let currentOrderId = null;
        let currentResultId = null;

        // Filter functionality for lab orders
        function filterOrders(status, clickedElement) {
            // Remove active class from all tabs
            const orderTabs = document.querySelectorAll('.filter-tab');
            orderTabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Add active class to clicked tab
            if (clickedElement) {
                clickedElement.classList.add('active');
            }

            // Show/hide order rows based on status
            const orders = document.querySelectorAll('#orders-tbody .order-row');
            let visibleCount = 0;

            orders.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = 'table-row';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Handle empty state
            const emptyRow = document.querySelector('#orders-tbody .empty-row');
            if (emptyRow) {
                emptyRow.style.display = visibleCount === 0 && orders.length > 0 ? 'table-row' : 'none';
            }

            // Show no results message if no orders match the filter
            if (visibleCount === 0 && orders.length > 0) {
                showNoResultsMessage('filter');
            } else {
                hideNoResultsMessage();
            }
        }

        // Handle Enter key press in search fields
        function handleSearchKeyPress(event, type) {
            if (event.key === 'Enter') {
                if (type === 'order') {
                    filterOrdersBySearch();
                } else if (type === 'result') {
                    filterResultsBySearch();
                }
            }
        }

        // Advanced filter functionality for lab orders
        function filterOrdersBySearch() {
            const searchTerm = document.getElementById('order-search').value.toLowerCase();
            const dateFrom = document.getElementById('order-date-from').value;
            const dateTo = document.getElementById('order-date-to').value;
            const statusFilter = document.getElementById('order-status-filter').value;

            const orders = document.querySelectorAll('#orders-tbody .order-row');
            let visibleCount = 0;

            orders.forEach(row => {
                let shouldShow = true;

                // Text search
                if (searchTerm) {
                    const rowText = row.textContent.toLowerCase();
                    if (!rowText.includes(searchTerm)) {
                        shouldShow = false;
                    }
                }

                // Date range filter
                if (dateFrom || dateTo) {
                    const rowDate = row.dataset.orderDate;
                    if (rowDate) {
                        const orderDate = new Date(rowDate).toISOString().split('T')[0];
                        if (dateFrom && orderDate < dateFrom) shouldShow = false;
                        if (dateTo && orderDate > dateTo) shouldShow = false;
                    }
                }

                // Status filter
                if (statusFilter && row.dataset.status !== statusFilter) {
                    shouldShow = false;
                }

                row.style.display = shouldShow ? 'table-row' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no orders match the filter
            if (visibleCount === 0 && orders.length > 0) {
                showNoResultsMessage('search');
            } else {
                hideNoResultsMessage();
            }

            // Clear tab selections when using search
            if (searchTerm || dateFrom || dateTo || statusFilter) {
                const orderTabs = document.querySelectorAll('.filter-tab');
                orderTabs.forEach(tab => {
                    tab.classList.remove('active');
                });
            }
        }

        // Advanced filter functionality for lab results
        function filterResultsBySearch() {
            const searchTerm = document.getElementById('result-search').value.toLowerCase();
            const dateFrom = document.getElementById('result-date-from').value;
            const dateTo = document.getElementById('result-date-to').value;

            const results = document.querySelectorAll('#results-tbody .result-row');
            let visibleCount = 0;

            results.forEach(row => {
                let shouldShow = true;

                // Text search
                if (searchTerm) {
                    const rowText = row.textContent.toLowerCase();
                    if (!rowText.includes(searchTerm)) {
                        shouldShow = false;
                    }
                }

                // Date range filter
                if (dateFrom || dateTo) {
                    const rowDate = row.dataset.resultDate;
                    if (rowDate) {
                        const resultDate = new Date(rowDate).toISOString().split('T')[0];
                        if (dateFrom && resultDate < dateFrom) shouldShow = false;
                        if (dateTo && resultDate > dateTo) shouldShow = false;
                    }
                }

                row.style.display = shouldShow ? 'table-row' : 'none';
                if (shouldShow) visibleCount++;
            });

            // Show no results message if no results match the filter
            if (visibleCount === 0 && results.length > 0) {
                showNoResultsMessage('search');
            } else {
                hideNoResultsMessage();
            }
        }

        // Clear lab order filters
        function clearOrderFilters() {
            document.getElementById('order-search').value = '';
            document.getElementById('order-date-from').value = '';
            document.getElementById('order-date-to').value = '';
            document.getElementById('order-status-filter').value = '';

            // Show all orders
            const orders = document.querySelectorAll('#orders-tbody .order-row');
            orders.forEach(row => {
                row.style.display = 'table-row';
            });

            // Reset filter tabs
            const orderTabs = document.querySelectorAll('.filter-tab');
            orderTabs.forEach(tab => {
                tab.classList.remove('active');
            });
            const allTab = orderTabs[0];
            if (allTab) allTab.classList.add('active');

            hideNoResultsMessage();
        }

        // Clear lab result filters
        function clearResultFilters() {
            document.getElementById('result-search').value = '';
            document.getElementById('result-date-from').value = '';
            document.getElementById('result-date-to').value = '';

            // Show all results
            const results = document.querySelectorAll('#results-tbody .result-row');
            results.forEach(row => {
                row.style.display = 'table-row';
            });

            hideNoResultsMessage();
        }

        // Show no results message
        function showNoResultsMessage(type) {
            hideNoResultsMessage(); // Remove existing message first
            
            const ordersTable = document.getElementById('orders-tbody');
            const resultsTable = document.getElementById('results-tbody');
            
            // Determine which table to show message in based on visible rows
            let targetTable = ordersTable;
            const orderRows = ordersTable.querySelectorAll('.order-row');
            const resultRows = resultsTable.querySelectorAll('.result-row');
            const visibleOrderRows = Array.from(orderRows).filter(row => row.style.display !== 'none');
            const visibleResultRows = Array.from(resultRows).filter(row => row.style.display !== 'none');
            
            if (visibleOrderRows.length === 0 && orderRows.length > 0) {
                targetTable = ordersTable;
            } else if (visibleResultRows.length === 0 && resultRows.length > 0) {
                targetTable = resultsTable;
            }
            
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="5" class="empty-state">
                    <i class="fas fa-${type === 'search' ? 'search' : 'filter'}"></i>
                    <h3>No matching lab ${targetTable === ordersTable ? 'orders' : 'results'} found</h3>
                    <p>${type === 'search' ? 'No lab data matches your search criteria. Try adjusting your filters.' : 'No lab data matches the selected filter. Try selecting a different status.'}</p>
                    <button type="button" class="btn btn-outline-secondary" onclick="${targetTable === ordersTable ? 'clearOrderFilters' : 'clearResultFilters'}()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </td>
            `;
            targetTable.appendChild(noResultsRow);
        }

        // Hide no results message
        function hideNoResultsMessage() {
            const noResultsRow = document.querySelector('.no-results-row');
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }

        // View lab order details
        function viewOrderDetails(orderId) {
            currentOrderId = orderId;
            const modal = document.getElementById('orderModal');
            const modalBody = document.getElementById('orderModalBody');
            
            // Show loading state
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading order details...</div>';
            modal.style.display = 'block';
            
            // Fetch order details via AJAX
            fetch(`get_lab_order_details.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayOrderDetails(data.order);
                    } else {
                        modalBody.innerHTML = `
                            <div class="error-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3>Error Loading Order</h3>
                                <p>${data.message || 'Failed to load order details.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="error-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Error Loading Order</h3>
                            <p>Failed to load order details. Please try again.</p>
                        </div>
                    `;
                });
        }

        // View lab result details
        function viewResultDetails(resultId) {
            currentResultId = resultId;
            const modal = document.getElementById('resultModal');
            const modalBody = document.getElementById('resultModalBody');
            
            // Show loading state
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading result details...</div>';
            modal.style.display = 'block';
            
            // Fetch result details via AJAX
            fetch(`get_lab_result_details.php?id=${resultId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayResultDetails(data.result);
                    } else {
                        modalBody.innerHTML = `
                            <div class="error-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3>Error Loading Result</h3>
                                <p>${data.message || 'Failed to load result details.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="error-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Error Loading Result</h3>
                            <p>Failed to load result details. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Display lab order details in modal
        function displayOrderDetails(order) {
            const modalBody = document.getElementById('orderModalBody');
            
            const statusClass = order.status.replace('_', '-');
            const statusText = order.status.toUpperCase().replace('_', ' ');
            
            modalBody.innerHTML = `
                <div class="order-details">
                    <div class="order-header">
                        <div class="order-info">
                            <h3>Lab Order #${order.lab_order_id}</h3>
                            <span class="status-badge status-${statusClass}">${statusText}</span>
                        </div>
                        <div class="order-meta">
                            <p><strong>Order Date:</strong> ${new Date(order.order_date).toLocaleDateString('en-US', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}</p>
                            <p><strong>Ordered by:</strong> ${order.doctor_name || 'Lab Direct Order'}</p>
                            ${order.consultation_date ? `<p><strong>Consultation:</strong> ${new Date(order.consultation_date).toLocaleDateString()}</p>` : ''}
                            ${order.appointment_date ? `<p><strong>Appointment:</strong> ${new Date(order.appointment_date).toLocaleDateString()}</p>` : ''}
                        </div>
                    </div>
                    
                    <div class="test-section">
                        <h4><i class="fas fa-vial"></i> Test Information</h4>
                        <div class="test-details">
                            <div class="detail-row">
                                <span class="label">Test Type:</span>
                                <span class="value">${order.test_type}</span>
                            </div>
                            ${order.specimen_type ? `
                                <div class="detail-row">
                                    <span class="label">Specimen Type:</span>
                                    <span class="value">${order.specimen_type}</span>
                                </div>
                            ` : ''}
                            ${order.test_description ? `
                                <div class="detail-row">
                                    <span class="label">Description:</span>
                                    <span class="value">${order.test_description}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    ${order.remarks ? `
                        <div class="order-remarks">
                            <h4><i class="fas fa-notes-medical"></i> Remarks</h4>
                            <p>${order.remarks}</p>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        // Display lab result details in modal
        function displayResultDetails(result) {
            const modalBody = document.getElementById('resultModalBody');
            
            modalBody.innerHTML = `
                <div class="result-details">
                    <div class="result-header">
                        <div class="result-info">
                            <h3>Lab Result #${result.lab_order_id}</h3>
                            <span class="status-badge status-completed">COMPLETED</span>
                        </div>
                        <div class="result-meta">
                            <p><strong>Result Date:</strong> ${new Date(result.result_date).toLocaleDateString('en-US', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}</p>
                            <p><strong>Order Date:</strong> ${new Date(result.order_date).toLocaleDateString()}</p>
                            <p><strong>Ordered by:</strong> ${result.doctor_name || 'Lab Direct Order'}</p>
                        </div>
                    </div>
                    
                    <div class="test-section">
                        <h4><i class="fas fa-vial"></i> Test Information</h4>
                        <div class="test-details">
                            <div class="detail-row">
                                <span class="label">Test Type:</span>
                                <span class="value">${result.test_type}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="result-section">
                        <h4><i class="fas fa-file-medical-alt"></i> Test Results</h4>
                        <div class="result-content">
                            ${result.result ? `<pre>${result.result}</pre>` : '<p>No result text available.</p>'}
                        </div>
                        ${result.result && (result.result.includes('.pdf') || result.result.includes('.jpg') || result.result.includes('.png') || result.result.includes('.jpeg') || result.result.includes('uploads/')) ? `
                            <div class="file-actions" style="margin-top: 15px; text-align: center;">
                                <button type="button" class="btn btn-outline-primary" onclick="viewResultFile('${result.result}')">
                                    <i class="fas fa-eye"></i> View File
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="downloadResultFile('${result.result}', ${result.lab_order_id})" style="margin-left: 10px;">
                                    <i class="fas fa-download"></i> Download File
                                </button>
                            </div>
                        ` : ''}
                    </div>
                    
                    ${result.remarks ? `
                        <div class="result-remarks">
                            <h4><i class="fas fa-notes-medical"></i> Remarks</h4>
                            <p>${result.remarks}</p>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        // Close order modal
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
            currentOrderId = null;
        }

        // Close result modal
        function closeResultModal() {
            document.getElementById('resultModal').style.display = 'none';
            currentResultId = null;
        }

        // View result file in new tab
        function viewResultFile(filePath) {
            window.open(filePath, '_blank');
        }

        // Download result file securely
        function downloadResultFile(filePath, resultId) {
            // Use secure download handler
            window.location.href = `download_lab_file.php?file=${encodeURIComponent(filePath)}&id=${resultId}`;
        }

        // Print result file
        function printResultFile(filePath) {
            const printWindow = window.open(filePath, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        }

        // Print lab result
        function printResult(resultId) {
            window.open(`print_lab_result.php?id=${resultId}`, '_blank');
        }

        // Print current result (from modal)
        function printCurrentResult() {
            if (currentResultId) {
                printResult(currentResultId);
            }
        }

        // Download lab history
        function downloadLabHistory() {
            window.location.href = 'download_lab_history.php';
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set default active filter for lab orders
            const orderTabs = document.querySelectorAll('.filter-tab');
            if (orderTabs.length > 0) {
                orderTabs[0].classList.add('active');
            }

            // Close modals when clicking outside
            window.onclick = function(event) {
                const orderModal = document.getElementById('orderModal');
                const resultModal = document.getElementById('resultModal');
                
                if (event.target === orderModal) {
                    closeOrderModal();
                }
                if (event.target === resultModal) {
                    closeResultModal();
                }
            }
        });
    </script>

    <!-- Alert Styles -->
    <style>
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #d1f2eb;
            color: #0d5e3d;
            border: 1px solid #7fb069;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b2b7;
        }

        .alert i {
            font-size: 1.1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .modal-header i {
            margin-right: 0.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #ccc;
        }

        .modal-body {
            padding: 2rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .error-state {
            text-align: center;
            padding: 2rem;
            color: #dc3545;
        }

        .error-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .prescription-details {
            line-height: 1.6;
        }

        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f3f4;
        }

        .prescription-info h3 {
            margin: 0 0 0.5rem 0;
            color: #0077b6;
            font-size: 1.3rem;
        }

        .prescription-meta {
            text-align: right;
            color: #6c757d;
        }

        .prescription-meta p {
            margin: 0.2rem 0;
            font-size: 0.9rem;
        }

        .prescription-remarks {
            background: #f8f9fa;
            border-left: 4px solid #0077b6;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 0 8px 8px 0;
        }

        .prescription-remarks h4 {
            margin: 0 0 0.5rem 0;
            color: #0077b6;
            font-size: 1rem;
        }

        .prescription-remarks p {
            margin: 0;
            color: #495057;
        }

        .medications-section h4 {
            margin: 0 0 1rem 0;
            color: #0077b6;
            font-size: 1.1rem;
        }

        .medications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .medication-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
        }

        .medication-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .medication-header h5 {
            margin: 0;
            color: #0077b6;
            font-size: 1rem;
            font-weight: 600;
        }

        .medication-status {
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
        }

        .medication-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-row .label {
            font-weight: 600;
            color: #495057;
            min-width: 80px;
        }

        .detail-row .value {
            color: #6c757d;
            flex: 1;
        }

        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }

            .modal-header {
                padding: 1rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-footer {
                padding: 1rem;
                flex-direction: column;
            }

            .prescription-header {
                flex-direction: column;
                gap: 1rem;
            }

            .prescription-meta {
                text-align: left;
            }

            .medication-details {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }

            .prescription-table {
                min-width: 600px;
            }

            .action-buttons-cell {
                flex-direction: column;
            }
        }
    </style>

</body>

</html>