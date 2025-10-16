<?php
// Include employee session configuration
// Use absolute path resolution
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Use relative path for assets - more reliable than absolute URLs
$assets_path = '../../assets';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../auth/employee_login.php");
    exit();
}

// Set active page for sidebar highlighting
$activePage = 'prescription_management';

// Define role-based permissions for prescription management
$canViewPrescriptions = in_array($_SESSION['role'], ['admin', 'doctor', 'nurse', 'pharmacist']);
$canDispensePrescriptions = $_SESSION['role'] === 'pharmacist' || $_SESSION['role'] === 'admin';
$canCreatePrescriptions = in_array($_SESSION['role'], ['admin', 'doctor']);
// Role IDs: 1 = Admin, 9 = Pharmacist
$canUpdateMedications = isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 9]);

if (!$canViewPrescriptions) {
    $role = $_SESSION['role'];
    header("Location: ../management/$role/dashboard.php");
    exit();
}

// Handle AJAX requests for search and filter
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$barangayFilter = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'active';  // Default to active prescriptions
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = 15;
$offset = ($page - 1) * $recordsPerPage;

// Check if prescriptions table exists, create basic structure assumption
// Note: This assumes a prescriptions table structure - adjust based on actual database schema
$prescriptionsSql = "SELECT p.prescription_id, p.patient_id, 
                     COALESCE(p.prescribed_date, p.prescription_date, p.created_at) as prescribed_date, 
                     p.status, p.prescribed_by_employee_id, 
                     COALESCE(p.instructions, p.remarks) as instructions,
                     pt.first_name, pt.last_name, pt.middle_name, 
                     COALESCE(pt.username, pt.patient_id) as patient_id_display, 
                     pt.barangay,
                     e.first_name as prescribed_by_first_name, e.last_name as prescribed_by_last_name
              FROM prescriptions p
              LEFT JOIN patients pt ON p.patient_id = pt.patient_id
              LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
              WHERE COALESCE(p.status, 'active') = 'active'";

$params = [];
$types = "";

if (!empty($searchQuery)) {
    $prescriptionsSql .= " AND (pt.first_name LIKE ? OR pt.last_name LIKE ? OR pt.username LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    array_push($params, $searchParam, $searchParam, $searchParam);
    $types .= "sss";
}

if (!empty($barangayFilter)) {
    $prescriptionsSql .= " AND pt.barangay = ?";
    array_push($params, $barangayFilter);
    $types .= "s";
}

if (!empty($statusFilter) && $statusFilter !== 'active') {
    $prescriptionsSql .= " AND p.status = ?";
    array_push($params, $statusFilter);
    $types .= "s";
}

if (!empty($dateFilter)) {
    $prescriptionsSql .= " AND DATE(COALESCE(p.prescribed_date, p.prescription_date, p.created_at)) = ?";
    array_push($params, $dateFilter);
    $types .= "s";
}

$prescriptionsSql .= " ORDER BY COALESCE(p.prescribed_date, p.prescription_date, p.created_at) DESC 
                       LIMIT ? OFFSET ?";
array_push($params, $recordsPerPage, $offset);
$types .= "ii";

// Handle case where prescriptions table might not exist yet
$prescriptionsResult = null;
try {
    $prescriptionsStmt = $conn->prepare($prescriptionsSql);
    if ($prescriptionsStmt === false) {
        // Query preparation failed - likely table doesn't exist
        throw new Exception("Failed to prepare prescriptions query: " . $conn->error);
    }
    
    if (!empty($types)) {
        $prescriptionsStmt->bind_param($types, ...$params);
    }
    $prescriptionsStmt->execute();
    $prescriptionsResult = $prescriptionsStmt->get_result();
} catch (Exception $e) {
    // Table doesn't exist yet or query failed - we'll show empty results
    error_log("Prescription Management Error: " . $e->getMessage());
    $prescriptionsResult = null;
}

// Recently dispensed prescriptions query - prescriptions with status = 'dispensed'
$recentDispensedSql = "SELECT p.prescription_id, 
                       COALESCE(p.prescribed_date, p.prescription_date, p.created_at) as prescription_date,
                       COALESCE(p.updated_at, p.dispensed_date) as dispensed_date,
                       pt.first_name, pt.last_name, pt.middle_name, 
                       COALESCE(pt.username, pt.patient_id) as patient_id_display,
                       e.first_name as doctor_first_name, e.last_name as doctor_last_name,
                       '' as pharmacist_first_name, '' as pharmacist_last_name
                       FROM prescriptions p 
                       LEFT JOIN patients pt ON p.patient_id = pt.patient_id
                       LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
                       WHERE COALESCE(p.status, 'active') = 'dispensed'
                       ORDER BY COALESCE(p.updated_at, p.dispensed_date, p.created_at) DESC
                       LIMIT 20";

$recentDispensedResult = null;
try {
    $recentDispensedStmt = $conn->prepare($recentDispensedSql);
    if ($recentDispensedStmt === false) {
        // Query preparation failed - likely table doesn't exist
        throw new Exception("Failed to prepare recent dispensed query: " . $conn->error);
    }
    
    $recentDispensedStmt->execute();
    $recentDispensedResult = $recentDispensedStmt->get_result();
} catch (Exception $e) {
    // Table doesn't exist yet or query failed - we'll show empty results
    error_log("Recent Dispensed Query Error: " . $e->getMessage());
    $recentDispensedResult = null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Management - WBHSMS</title>
    <link rel="stylesheet" href="<?= $assets_path ?>/css/sidebar.css">
    <link rel="stylesheet" href="<?= $assets_path ?>/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Content wrapper and page layout */
        .content-wrapper {
            margin-left: 300px;
            padding: 20px;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 10px;
            }
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .page-header h1 {
            color: #0077b6;
            margin: 0;
            font-size: 1.8rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total {
            border-left: 5px solid #6c757d;
        }

        .stat-card.pending {
            border-left: 5px solid #ffc107;
        }

        .stat-card.active {
            border-left: 5px solid #17a2b8;
        }

        .stat-card.completed {
            border-left: 5px solid #28a745;
        }

        .stat-card.voided {
            border-left: 5px solid #dc3545;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #03045e;
        }

        .stat-label {
            font-size: 0.9em;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #0077b6;
            color: white;
        }

        .btn-primary:hover {
            background-color: #005577;
        }

        /* Prescription Management Specific Styles */
        .prescription-management-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .prescription-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #03045e;
        }

        .panel-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #03045e;
        }

        .create-prescription-btn {
            background: #03045e;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s;
        }

        .create-prescription-btn:hover {
            background: #0218A7;
        }

        .prescription-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .prescription-table th,
        .prescription-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 0.9em;
        }

        .prescription-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #03045e;
        }

        .prescription-table tr:hover {
            background-color: #f5f5f5;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-dispensing {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-dispensed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-partial {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .action-btn {
            padding: 5px 10px;
            margin: 2px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.3s;
        }

        .btn-view {
            background-color: #007bff;
            color: white;
        }

        .btn-dispense {
            background-color: #28a745;
            color: white;
        }

        .btn-print {
            background-color: #17a2b8;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }

        .search-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: stretch;
        }

        .filter-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
            flex: 1;
            min-width: 150px;
        }

        .filter-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .search-btn {
            background-color: #0077b6;
            color: white;
        }

        .search-btn:hover {
            background-color: #005577;
        }

        .clear-btn {
            background-color: #6c757d;
            color: white;
        }

        .clear-btn:hover {
            background-color: #545b62;
        }

        .progress-bar {
            width: 60px;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: #007bff;
            transition: width 0.3s;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            max-width: 80%;
            max-height: 80%;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .close-btn {
            font-size: 1.5em;
            cursor: pointer;
            color: #aaa;
            border: none;
            background: none;
        }

        .close-btn:hover {
            color: #000;
        }

        /* Empty state styles */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }

        .empty-state p {
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .prescription-management-container {
                grid-template-columns: 1fr;
            }

            .search-filters {
                flex-direction: column;
            }

            .filter-input {
                min-width: 100%;
            }
        }

        /* Alert Styles */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .btn-close:hover {
            opacity: 1;
        }

        /* Print-specific styles */
        @media print {
            .no-print {
                display: none !important;
            }

            .modal {
                display: block !important;
                position: static !important;
                background: white !important;
            }

            .modal-content.print-modal {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-prescription {
                font-family: 'Times New Roman', serif;
                color: black !important;
                background: white !important;
                page-break-inside: avoid;
            }

            .print-header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }

            .print-logo {
                width: 80px;
                height: auto;
                margin-bottom: 10px;
            }

            .prescription-medications-print {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            .prescription-medications-print th,
            .prescription-medications-print td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
                font-size: 12px;
            }

            .prescription-medications-print th {
                background-color: #f0f0f0;
                font-weight: bold;
            }

            .signature-section {
                margin-top: 40px;
                display: flex;
                justify-content: space-between;
            }

            .signature-box {
                width: 45%;
                text-align: center;
                border-top: 1px solid #000;
                padding-top: 10px;
                margin-top: 40px;
            }
        }
    </style>
</head>

<body>
    <!-- Set active page for sidebar highlighting -->
    <?php $activePage = 'prescription_management'; ?>
    
    <!-- Include role-based sidebar -->
    <?php 
    require_once $root_path . '/includes/dynamic_sidebar_helper.php';
    includeDynamicSidebar($activePage, $root_path);
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="<?= getRoleDashboardUrl() ?>"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Prescription Management</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-pills"></i> Prescription Management</h1>
            <?php if ($canCreatePrescriptions): ?>
            <a href="#" class="btn btn-primary" onclick="openCreatePrescriptionModal(); return false;">
                <i class="fas fa-plus"></i> Create Prescription
            </a>
            <?php endif; ?>
        </div>

        <!-- Success/Error Messages -->
        <div id="alertContainer"></div>

        <!-- Prescription Statistics -->
        <div class="stats-grid">
            <?php
            // Get prescription statistics - using default values since table may not exist yet
            $prescription_stats = [
                'total' => 0,
                'pending' => 0,
                'dispensing' => 0,
                'dispensed' => 0,
                'cancelled' => 0
            ];

            try {
                $stats_sql = "SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN COALESCE(status, 'active') = 'pending' THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN COALESCE(status, 'active') = 'dispensing' THEN 1 ELSE 0 END) as dispensing,
                                    SUM(CASE WHEN COALESCE(status, 'active') = 'dispensed' THEN 1 ELSE 0 END) as dispensed,
                                    SUM(CASE WHEN COALESCE(status, 'active') = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                              FROM prescriptions WHERE DATE(COALESCE(prescribed_date, prescription_date, created_at)) >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)";

                $stats_result = $conn->query($stats_sql);
                if ($stats_result && $row = $stats_result->fetch_assoc()) {
                    $prescription_stats = $row;
                }
            } catch (Exception $e) {
                // Use default values if query fails (table doesn't exist)
            }
            ?>

            <div class="stat-card total">
                <div class="stat-number"><?= number_format($prescription_stats['total']) ?></div>
                <div class="stat-label">Total Prescriptions (30 days)</div>
            </div>

            <div class="stat-card pending">
                <div class="stat-number"><?= number_format($prescription_stats['pending']) ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card active">
                <div class="stat-number"><?= number_format($prescription_stats['dispensing']) ?></div>
                <div class="stat-label">Being Dispensed</div>
            </div>

            <div class="stat-card completed">
                <div class="stat-number"><?= number_format($prescription_stats['dispensed']) ?></div>
                <div class="stat-label">Dispensed</div>
            </div>

            <div class="stat-card voided">
                <div class="stat-number"><?= number_format($prescription_stats['cancelled']) ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <div class="prescription-management-container">
            <!-- Left Panel: All Prescriptions -->
            <div class="prescription-panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-prescription-bottle-alt"></i> All Prescriptions
                    </div>
                </div>

                <!-- View Available Medications Button -->
                <div style="margin-bottom: 15px;">
                    <button type="button" class="btn btn-primary" onclick="openMedicationsModal()">
                        <i class="fas fa-pills"></i> View Available Medications
                    </button>
                </div>

                <!-- Search and Filter Controls -->
                <div class="search-filters">
                    <input type="text" class="filter-input" id="searchPrescriptions" placeholder="Search patient name..." value="<?= htmlspecialchars($searchQuery) ?>">
                    <select class="filter-input" id="barangayFilter">
                        <option value="">All Barangays</option>
                        <?php
                        // Get unique barangays from patients table
                        try {
                            $barangayQuery = "SELECT DISTINCT barangay FROM patients WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay";
                            $barangayResult = $conn->query($barangayQuery);
                            if ($barangayResult) {
                                while ($barangay = $barangayResult->fetch_assoc()) {
                                    $selected = $barangayFilter === $barangay['barangay'] ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($barangay['barangay']) . "' $selected>" . htmlspecialchars($barangay['barangay']) . "</option>";
                                }
                            }
                        } catch (Exception $e) {
                            // If patients table doesn't exist, show default options
                            echo "<option value='Poblacion'>Poblacion</option>";
                            echo "<option value='Zone I'>Zone I</option>";
                            echo "<option value='Zone II'>Zone II</option>";
                            echo "<option value='Zone III'>Zone III</option>";
                            echo "<option value='Zone IV'>Zone IV</option>";
                        }
                        ?>
                    </select>
                    <input type="date" class="filter-input" id="dateFilter" placeholder="Prescribed Date" value="<?= htmlspecialchars($dateFilter) ?>">
                    <select class="filter-input" id="statusFilter">
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Prescriptions</option>
                        <option value="dispensed" <?= $statusFilter === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                        <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="search-filters" style="justify-content: flex-end; margin-top: -10px;">
                    <button type="button" class="filter-btn search-btn" id="searchBtn" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <button type="button" class="filter-btn clear-btn" id="clearBtn" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>

                <!-- Prescriptions Table -->
                <?php if ($prescriptionsResult && $prescriptionsResult->num_rows > 0): ?>
                <table class="prescription-table">
                    <thead>
                        <tr>
                            <th>Prescription ID</th>
                            <th>Patient Name</th>
                            <th>Prescribing Doctor</th>
                            <th>Date Prescribed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($prescription = $prescriptionsResult->fetch_assoc()): ?>
                            <?php
                            $patientName = trim($prescription['first_name'] . ' ' . $prescription['middle_name'] . ' ' . $prescription['last_name']);
                            $doctorName = trim($prescription['prescribed_by_first_name'] . ' ' . $prescription['prescribed_by_last_name']);
                            ?>
                            <tr>
                                <td>
                                    <strong>RX-<?= sprintf('%06d', $prescription['prescription_id']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($patientName) ?></strong><br>
                                    <small>ID: <?= htmlspecialchars($prescription['patient_id_display']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($doctorName ?: 'Unknown') ?></strong>
                                </td>
                                <td><?= date('M d, Y', strtotime($prescription['prescribed_date'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $prescription['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $prescription['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn btn-view" onclick="viewUpdatePrescription(<?= $prescription['prescription_id'] ?>)">
                                        <i class="fas fa-edit"></i> View / Update
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <h3>No Prescriptions Found</h3>
                    <p>No prescriptions match your current filters or none have been created yet.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Panel: Recently Dispensed -->
            <div class="prescription-panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-history"></i> Recently Dispensed
                    </div>
                </div>

                <!-- Recently Dispensed Prescriptions Table -->
                <?php if ($recentDispensedResult && $recentDispensedResult->num_rows > 0): ?>
                <table class="prescription-table">
                    <thead>
                        <tr>
                            <th>Prescription ID</th>
                            <th>Patient Name</th>
                            <th>Date Dispensed</th>
                            <th>Pharmacist Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($dispensed = $recentDispensedResult->fetch_assoc()): ?>
                            <?php
                            $patientName = trim($dispensed['first_name'] . ' ' . $dispensed['middle_name'] . ' ' . $dispensed['last_name']);
                            $pharmacistName = trim($dispensed['pharmacist_first_name'] . ' ' . $dispensed['pharmacist_last_name']);
                            ?>
                            <tr>
                                <td>
                                    <strong>RX-<?= sprintf('%06d', $dispensed['prescription_id']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($patientName) ?></strong><br>
                                    <small>ID: <?= htmlspecialchars($dispensed['patient_id_display']) ?></small>
                                </td>
                                <td><?= date('M d, Y', strtotime($dispensed['dispensed_date'])) ?></td>
                                <td><?= htmlspecialchars($pharmacistName ?: 'System') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewDispensedPrescription(<?= $dispensed['prescription_id'] ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="printPrescription(<?= $dispensed['prescription_id'] ?>)">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-prescription-bottle"></i>
                    <h3>No Recently Dispensed Prescriptions</h3>
                    <p>No prescriptions have been dispensed recently.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Available Medications Modal -->
    <div id="availableMedicationsModal" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div class="modal-header">
                <h3><i class="fas fa-pills"></i> PhilHealth GAMOT 2025 - Available Medications</h3>
                <button class="close-btn" onclick="closeModal('availableMedicationsModal')">&times;</button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div style="margin-bottom: 15px;">
                    <input type="text" id="medicationSearch" placeholder="Search medications..." 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                           onkeyup="filterMedications()">
                </div>
                <table class="prescription-table" id="medicationsTable">
                    <thead>
                        <tr>
                            <th>Drug Name</th>
                            <th>Dosage Strength</th>
                            <th>Formulation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- PhilHealth GAMOT 2025 Medications List -->
                        <tr><td>Paracetamol</td><td>500mg</td><td>Tablet</td></tr>
                        <tr><td>Ibuprofen</td><td>400mg</td><td>Tablet</td></tr>
                        <tr><td>Amoxicillin</td><td>500mg</td><td>Capsule</td></tr>
                        <tr><td>Cetirizine</td><td>10mg</td><td>Tablet</td></tr>
                        <tr><td>Losartan</td><td>50mg</td><td>Tablet</td></tr>
                        <tr><td>Amlodipine</td><td>5mg</td><td>Tablet</td></tr>
                        <tr><td>Metformin</td><td>500mg</td><td>Tablet</td></tr>
                        <tr><td>Simvastatin</td><td>20mg</td><td>Tablet</td></tr>
                        <tr><td>Omeprazole</td><td>20mg</td><td>Capsule</td></tr>
                        <tr><td>Salbutamol</td><td>2mg</td><td>Tablet</td></tr>
                        <tr><td>Ferrous Sulfate</td><td>325mg</td><td>Tablet</td></tr>
                        <tr><td>Mefenamic Acid</td><td>250mg</td><td>Capsule</td></tr>
                        <tr><td>Co-trimoxazole</td><td>400mg/80mg</td><td>Tablet</td></tr>
                        <tr><td>Dextromethorphan</td><td>15mg</td><td>Syrup</td></tr>
                        <tr><td>Multivitamins</td><td>Various</td><td>Tablet</td></tr>
                        <tr><td>Oral Rehydration Salt</td><td>21.0g</td><td>Powder</td></tr>
                        <tr><td>Zinc Sulfate</td><td>20mg</td><td>Tablet</td></tr>
                        <tr><td>Calcium Carbonate</td><td>500mg</td><td>Tablet</td></tr>
                        <tr><td>Aspirin</td><td>80mg</td><td>Tablet</td></tr>
                        <tr><td>Atenolol</td><td>50mg</td><td>Tablet</td></tr>
                        <tr><td>Furosemide</td><td>40mg</td><td>Tablet</td></tr>
                        <tr><td>Captopril</td><td>25mg</td><td>Tablet</td></tr>
                        <tr><td>Gliclazide</td><td>80mg</td><td>Tablet</td></tr>
                        <tr><td>Insulin (NPH)</td><td>100 IU/ml</td><td>Vial</td></tr>
                        <tr><td>Insulin (Regular)</td><td>100 IU/ml</td><td>Vial</td></tr>
                        <tr><td>Ranitidine</td><td>150mg</td><td>Tablet</td></tr>
                        <tr><td>Diclofenac</td><td>50mg</td><td>Tablet</td></tr>
                        <tr><td>Prednisolone</td><td>5mg</td><td>Tablet</td></tr>
                        <tr><td>Dexamethasone</td><td>0.5mg</td><td>Tablet</td></tr>
                        <tr><td>Hydrochlorothiazide</td><td>25mg</td><td>Tablet</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View/Update Prescription Modal -->
    <div id="viewUpdatePrescriptionModal" class="modal">
        <div class="modal-content" style="max-width: 95%; max-height: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-prescription"></i> View / Update Prescription</h3>
                <button class="close-btn" onclick="closeModal('viewUpdatePrescriptionModal')">&times;</button>
            </div>
            <div id="viewUpdatePrescriptionBody">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- View Dispensed Prescription Modal -->
    <div id="viewDispensedModal" class="modal">
        <div class="modal-content" style="max-width: 95%; max-height: 95%;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Dispensed Prescription Details</h3>
                <button class="close-btn" onclick="closeModal('viewDispensedModal')">&times;</button>
            </div>
            <div id="viewDispensedBody">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Print Prescription Modal -->
    <div id="printPrescriptionModal" class="modal">
        <div class="modal-content print-modal" style="max-width: 95%; max-height: 95%;">
            <div class="modal-header no-print">
                <h3><i class="fas fa-print"></i> Print Prescription</h3>
                <button class="close-btn" onclick="closeModal('printPrescriptionModal')">&times;</button>
            </div>
            <div id="printPrescriptionBody">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer no-print">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Prescription
                </button>
                <button class="btn btn-secondary" onclick="closeModal('printPrescriptionModal')">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Prescription Details Modal -->
    <div id="prescriptionDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Prescription Details</h3>
                <button class="close-btn" onclick="closeModal('prescriptionDetailsModal')">&times;</button>
            </div>
            <div id="modalBody">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Create Prescription Modal -->
    <div id="createPrescriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Prescription</h3>
                <button class="close-btn" onclick="closeModal('createPrescriptionModal')">&times;</button>
            </div>
            <div id="createPrescriptionBody">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Dispense Prescription Modal -->
    <div id="dispensePrescriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dispense Prescription</h3>
                <button class="close-btn" onclick="closeModal('dispensePrescriptionModal')">&times;</button>
            </div>
            <div id="dispensePrescriptionBody">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <script>
        // Prescription Management JavaScript Functions

        // Search and filter functionality
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
        document.getElementById('barangayFilter').addEventListener('change', applyFilters);
        document.getElementById('dateFilter').addEventListener('change', applyFilters);
        
        // Allow Enter key to trigger search
        document.getElementById('searchPrescriptions').addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        });

        function applyFilters() {
            const search = document.getElementById('searchPrescriptions').value.trim();
            const barangay = document.getElementById('barangayFilter').value;
            const status = document.getElementById('statusFilter').value;
            const date = document.getElementById('dateFilter').value;

            const params = new URLSearchParams();
            if (search) params.set('search', search);
            if (barangay) params.set('barangay', barangay);
            if (status) params.set('status', status);
            if (date) params.set('date', date);

            window.location.href = '?' + params.toString();
        }

        function clearFilters() {
            document.getElementById('searchPrescriptions').value = '';
            document.getElementById('barangayFilter').value = '';
            document.getElementById('statusFilter').value = 'active'; // Default to active
            document.getElementById('dateFilter').value = '';
            
            // Redirect to page without any filters
            window.location.href = window.location.pathname;
        }

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function viewPrescriptionDetails(prescriptionId) {
            document.getElementById('prescriptionDetailsModal').style.display = 'block';
            document.getElementById('modalBody').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            fetch(`api/get_prescription_details.php?prescription_id=${prescriptionId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalBody').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('modalBody').innerHTML = '<div class="alert alert-error">Error loading prescription details.</div>';
                });
        }

        function openCreatePrescriptionModal() {
            <?php if ($canCreatePrescriptions): ?>
                document.getElementById('createPrescriptionModal').style.display = 'block';
                document.getElementById('createPrescriptionBody').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

                fetch('create_prescription.php')
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('createPrescriptionBody').innerHTML = html;

                        // Execute any scripts that were loaded with the content
                        const scripts = document.getElementById('createPrescriptionBody').getElementsByTagName('script');
                        for (let i = 0; i < scripts.length; i++) {
                            const script = scripts[i];
                            const newScript = document.createElement('script');

                            if (script.src) {
                                newScript.src = script.src;
                            } else {
                                newScript.textContent = script.textContent;
                            }

                            document.head.appendChild(newScript);
                            script.parentNode.removeChild(script);
                        }

                        console.log('Create prescription modal content loaded and scripts executed');
                    })
                    .catch(error => {
                        console.error('Error loading create prescription form:', error);
                        document.getElementById('createPrescriptionBody').innerHTML = '<div class="alert alert-error">Error loading create prescription form.</div>';
                    });
            <?php else: ?>
                showAlert('You are not authorized to create prescriptions.', 'error');
            <?php endif; ?>
        }

        function dispensePrescription(prescriptionId) {
            <?php if ($canDispensePrescriptions): ?>
                document.getElementById('dispensePrescriptionModal').style.display = 'block';
                document.getElementById('dispensePrescriptionBody').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

                fetch(`dispense_prescription.php?prescription_id=${prescriptionId}`)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('dispensePrescriptionBody').innerHTML = html;
                    })
                    .catch(error => {
                        document.getElementById('dispensePrescriptionBody').innerHTML = '<div class="alert alert-error">Error loading dispensing form.</div>';
                    });
            <?php else: ?>
                showAlert('You are not authorized to dispense prescriptions.', 'error');
            <?php endif; ?>
        }

        // New functions for prescription management
        function openMedicationsModal() {
            document.getElementById('availableMedicationsModal').style.display = 'block';
        }

        // View dispensed prescription function
        function viewDispensedPrescription(prescriptionId) {
            document.getElementById('viewDispensedModal').style.display = 'block';
            document.getElementById('viewDispensedBody').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            fetch(`api/get_dispensed_prescription_view.php?prescription_id=${prescriptionId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('viewDispensedBody').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('viewDispensedBody').innerHTML = '<div class="alert alert-error">Error loading prescription details.</div>';
                });
        }

        // Print prescription function
        function printPrescription(prescriptionId) {
            document.getElementById('printPrescriptionModal').style.display = 'block';
            document.getElementById('printPrescriptionBody').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            fetch(`api/get_printable_prescription.php?prescription_id=${prescriptionId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('printPrescriptionBody').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('printPrescriptionBody').innerHTML = '<div class="alert alert-error">Error loading prescription for printing.</div>';
                });
        }

        function filterMedications() {
            const searchTerm = document.getElementById('medicationSearch').value.toLowerCase();
            const table = document.getElementById('medicationsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().includes(searchTerm)) {
                        match = true;
                        break;
                    }
                }
                
                rows[i].style.display = match ? '' : 'none';
            }
        }

        function viewUpdatePrescription(prescriptionId) {
            document.getElementById('viewUpdatePrescriptionModal').style.display = 'block';
            document.getElementById('viewUpdatePrescriptionBody').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            // Load prescription details with update form
            fetch(`api/get_prescription_update_form.php?prescription_id=${prescriptionId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('viewUpdatePrescriptionBody').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('viewUpdatePrescriptionBody').innerHTML = '<div class="alert alert-error">Error loading prescription details.</div>';
                });
        }

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <span><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}</span>
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            `;
            alertContainer.appendChild(alertDiv);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = ['prescriptionDetailsModal', 'createPrescriptionModal', 'dispensePrescriptionModal', 'availableMedicationsModal', 'viewUpdatePrescriptionModal', 'viewDispensedModal', 'printPrescriptionModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });

        // Handle server-side messages
        <?php if (isset($_SESSION['prescription_message'])): ?>
            showAlert('<?= addslashes($_SESSION['prescription_message']) ?>', '<?= $_SESSION['prescription_message_type'] ?? 'success' ?>');
        <?php
            unset($_SESSION['prescription_message']);
            unset($_SESSION['prescription_message_type']);
        endif; ?>
    </script>
</body>

</html>