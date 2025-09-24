<?php
// referrals_management.php - Admin Side
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check if role is authorized
$authorized_roles = ['doctor', 'bhw', 'dho', 'records_officer', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: dashboard.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Handle status updates and actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $referral_id = $_POST['referral_id'] ?? '';

    if (!empty($referral_id) && is_numeric($referral_id)) {
        try {
            switch ($action) {
                case 'complete':
                    $stmt = $conn->prepare("UPDATE referrals SET status = 'completed' WHERE id = ?");
                    $stmt->bind_param("i", $referral_id);
                    $stmt->execute();
                    $message = "Referral marked as completed successfully.";
                    $stmt->close();
                    break;

                case 'void':
                    $void_reason = trim($_POST['void_reason'] ?? '');
                    if (empty($void_reason)) {
                        $error = "Void reason is required.";
                    } else {
                        $stmt = $conn->prepare("UPDATE referrals SET status = 'voided' WHERE id = ?");
                        $stmt->bind_param("i", $referral_id);
                        $stmt->execute();
                        $message = "Referral voided successfully.";
                        $stmt->close();
                    }
                    break;

                case 'reactivate':
                    $stmt = $conn->prepare("UPDATE referrals SET status = 'active' WHERE id = ?");
                    $stmt->bind_param("i", $referral_id);
                    $stmt->execute();
                    $message = "Referral reactivated successfully.";
                    $stmt->close();
                    break;
            }
        } catch (Exception $e) {
            $error = "Failed to update referral: " . $e->getMessage();
        }
    }
}

// Fetch referrals with patient information
$patient_id = $_GET['patient_id'] ?? '';
$first_name = $_GET['first_name'] ?? '';
$last_name = $_GET['last_name'] ?? '';
$barangay = $_GET['barangay'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($patient_id)) {
    $where_conditions[] = "p.username LIKE ?";
    $patient_id_term = "%$patient_id%";
    $params[] = $patient_id_term;
    $param_types .= 's';
}

if (!empty($first_name)) {
    $where_conditions[] = "p.first_name LIKE ?";
    $first_name_term = "%$first_name%";
    $params[] = $first_name_term;
    $param_types .= 's';
}

if (!empty($last_name)) {
    $where_conditions[] = "p.last_name LIKE ?";
    $last_name_term = "%$last_name%";
    $params[] = $last_name_term;
    $param_types .= 's';
}

if (!empty($barangay)) {
    $where_conditions[] = "b.barangay_name LIKE ?";
    $barangay_term = "%$barangay%";
    $params[] = $barangay_term;
    $param_types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

try {
    $sql = "
        SELECT r.*, 
               p.first_name, p.middle_name, p.last_name, p.username as patient_number, 
               b.barangay_name as barangay,
               e.first_name as issuer_first_name, e.last_name as issuer_last_name
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN employees e ON r.referred_by = e.employee_id
        $where_clause
        ORDER BY r.referral_date DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $referrals = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch referrals: " . $e->getMessage();
    $referrals = [];
}

// Get statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'completed' => 0,
    'pending' => 0,
    'voided' => 0
];

try {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM referrals GROUP BY status");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
        $stats['total'] += $row['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    // Ignore errors for stats
}

// Fetch barangays for dropdown
$barangays = [];
try {
    $stmt = $conn->prepare("SELECT barangay_id, barangay_name FROM barangay WHERE status = 'active' ORDER BY barangay_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $barangays = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    // Ignore errors for barangays
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Referrals Management</title>
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
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card.total {
            border-left: 4px solid #0077b6;
        }

        .stat-card.active {
            border-left: 4px solid #43e97b;
        }

        .stat-card.completed {
            border-left: 4px solid #4facfe;
        }

        .stat-card.pending {
            border-left: 4px solid #f093fb;
        }

        .stat-card.voided {
            border-left: 4px solid #fa709a;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filters-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #0077b6;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; /* Ensures table doesn't get too compressed */
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            white-space: normal;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #0077b6;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.85rem;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
            }

            .table th {
                font-size: 0.75rem;
            }

            /* Hide less important columns on mobile */
            .table th:nth-child(3), /* Barangay */
            .table td:nth-child(3),
            .table th:nth-child(6), /* Status */
            .table td:nth-child(6),
            .table th:nth-child(8), /* Issued By */
            .table td:nth-child(8) {
                display: none;
            }

            /* Adjust remaining columns */
            .table th:nth-child(2), /* Patient */
            .table td:nth-child(2) {
                min-width: 120px;
            }

            .table th:nth-child(4), /* Chief Complaint */
            .table td:nth-child(4) {
                min-width: 150px;
                white-space: normal;
                word-wrap: break-word;
            }

            .table th:nth-child(7), /* Issued Date */
            .table td:nth-child(7) {
                min-width: 100px;
                font-size: 0.7rem;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            .table {
                min-width: 600px;
            }

            .table th,
            .table td {
                padding: 0.4rem 0.2rem;
                font-size: 0.75rem;
            }

            /* Show mobile-friendly layout */
            .mobile-card {
                display: none;
            }
        }

        /* Mobile card layout for very small screens */
        @media (max-width: 400px) {
            .table-wrapper {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .mobile-card {
                display: block;
                background: white;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                margin-bottom: 1rem;
                padding: 1rem;
            }

            .mobile-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.5rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #f0f0f0;
            }

            .mobile-card-body {
                font-size: 0.85rem;
                line-height: 1.4;
            }

            .mobile-card-field {
                margin-bottom: 0.5rem;
            }

            .mobile-card-label {
                font-weight: 600;
                color: #03045e;
                margin-right: 0.5rem;
            }
        }

        /* Scrollbar styling */
        .table-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-primary {
            background: #007bff;
            color: white;
        }

        .badge-success {
            background: #28a745;
            color: white;
        }

        .badge-info {
            background: #17a2b8;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #212529;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .badge-secondary {
            background: #6c757d;
            color: white;
        }

        .actions-group {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b2b7;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        @media (max-width: 600px) {
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .btn {
                justify-content: center;
            }
        }

        .referral-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .details-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }

        .details-section h4 {
            color: #03045e;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
            font-size: 1.1rem;
        }

        .detail-item {
            display: flex;
            margin-bottom: 0.75rem;
            align-items: flex-start;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
            margin-right: 0.5rem;
        }

        .detail-value {
            flex: 1;
            word-wrap: break-word;
        }

        .vitals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }

        .vitals-table th,
        .vitals-table td {
            padding: 0.5rem;
            text-align: left;
            border: 1px solid #dee2e6;
        }

        .vitals-table th {
            background: #e9ecef;
            font-weight: 600;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .breadcrumb a {
            color: #0077b6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }
        /* Mobile Cards for very small screens */
        .mobile-cards {
            padding: 0;
        }

        .mobile-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .mobile-card-header {
            background: #f8f9fa;
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

        .mobile-card-body {
            padding: 0.75rem;
        }

        .mobile-card-field {
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .mobile-card-field:last-child {
            margin-bottom: 0;
        }

        .mobile-card-label {
            font-weight: 600;
            color: #495057;
            display: inline-block;
            min-width: 80px;
        }

        /* Responsive badge adjustments */
        @media (max-width: 400px) {
            .badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .actions-group .btn {
                margin: 0.125rem;
            }
        }

        /* Additional responsive table improvements */
        @media (max-width: 768px) {
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.85rem;
            }
            
            /* Hide less important columns on medium screens */
            .table th:nth-child(3),
            .table td:nth-child(3),
            .table th:nth-child(8),
            .table td:nth-child(8) {
                display: none;
            }
        }

        @media (max-width: 480px) {
            /* Hide more columns on smaller screens */
            .table th:nth-child(4),
            .table td:nth-child(4),
            .table th:nth-child(5),
            .table td:nth-child(5) {
                display: none;
            }
            
            .table th,
            .table td {
                padding: 0.4rem 0.2rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'referrals';
    include '../../../includes/sidebar_admin.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Referrals Management</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-share"></i> Referrals Management</h1>
            <a href="create_referrals.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Referral
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Referrals</div>
            </div>
            <div class="stat-card active">
                <div class="stat-number"><?php echo number_format($stats['active'] ?? 0); ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-number"><?php echo number_format($stats['completed'] ?? 0); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card voided">
                <div class="stat-number"><?php echo number_format($stats['voided'] ?? 0); ?></div>
                <div class="stat-label">Voided</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label for="patient_id">Patient ID</label>
                    <input type="text" id="patient_id" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>"
                        placeholder="Enter patient ID...">
                </div>
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>"
                        placeholder="Enter first name...">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>"
                        placeholder="Enter last name...">
                </div>
                <div class="form-group">
                    <label for="barangay">Barangay</label>
                    <select id="barangay" name="barangay">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $brgy): ?>
                            <option value="<?php echo htmlspecialchars($brgy['barangay_name']); ?>" 
                                <?php echo $barangay === $brgy['barangay_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brgy['barangay_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="voided" <?php echo $status_filter === 'voided' ? 'selected' : ''; ?>>Voided</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="form-group">
                    <a href="?" class="btn btn-secondary" style="margin-top: 0.5rem;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Referrals Table -->
        <div class="table-container">
            <?php if (empty($referrals)): ?>
                <div class="empty-state">
                    <i class="fas fa-share"></i>
                    <h3>No Referrals Found</h3>
                    <p>No referrals match your current search criteria.</p>
                    <a href="create_referrals.php" class="btn btn-primary">Create First Referral</a>
                </div>
            <?php else: ?>
                <!-- Desktop/Tablet Table View -->
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Referral #</th>
                                <th>Patient</th>
                                <th>Barangay</th>
                                <th>Chief Complaint</th>
                                <th>Destination</th>
                                <th>Status</th>
                                <th>Issued Date</th>
                                <th>Issued By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referrals as $referral):
                                $patient_name = trim($referral['first_name'] . ' ' . ($referral['middle_name'] ? $referral['middle_name'] . ' ' : '') . $referral['last_name']);
                                $issuer_name = trim($referral['issuer_first_name'] . ' ' . $referral['issuer_last_name']);
                                $destination = $referral['destination_type'] === 'external' ? $referral['referred_to_external'] : 'Internal Facility';

                                // Determine badge class based on status
                                $badge_class = 'badge-secondary';
                                switch ($referral['status']) {
                                    case 'active':
                                        $badge_class = 'badge-success';
                                        break;
                                    case 'pending':
                                        $badge_class = 'badge-warning';
                                        break;
                                    case 'completed':
                                        $badge_class = 'badge-info';
                                        break;
                                    case 'voided':
                                        $badge_class = 'badge-danger';
                                        break;
                                    case 'expired':
                                        $badge_class = 'badge-secondary';
                                        break;
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($referral['referral_num']); ?></strong></td>
                                    <td>
                                        <div style="max-width: 150px;">
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($patient_name); ?></div>
                                            <small style="color: #6c757d;"><?php echo htmlspecialchars($referral['patient_number']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($referral['barangay'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div style="max-width: 200px; white-space: normal; word-wrap: break-word;">
                                            <?php echo htmlspecialchars($referral['chief_complaint']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="max-width: 150px; white-space: normal; word-wrap: break-word;">
                                            <?php echo htmlspecialchars($destination); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($referral['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem;">
                                            <?php echo date('M j, Y', strtotime($referral['date_of_referral'])); ?>
                                            <br><small><?php echo date('g:i A', strtotime($referral['date_of_referral'])); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($issuer_name); ?></td>
                                    <td>
                                        <div class="actions-group">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="viewReferral(<?php echo $referral['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($referral['status'] === 'voided'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="reactivate">
                                                    <input type="hidden" name="referral_id" value="<?php echo $referral['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Reactivate this referral?')" title="Reactivate">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View (for very small screens) -->
                <div class="mobile-cards" style="display: none;">
                    <?php foreach ($referrals as $referral):
                        $patient_name = trim($referral['first_name'] . ' ' . ($referral['middle_name'] ? $referral['middle_name'] . ' ' : '') . $referral['last_name']);
                        $issuer_name = trim($referral['issuer_first_name'] . ' ' . $referral['issuer_last_name']);
                        $destination = $referral['destination_type'] === 'external' ? $referral['referred_to_external'] : 'Internal Facility';

                        // Determine badge class based on status
                        $badge_class = 'badge-secondary';
                        switch ($referral['status']) {
                            case 'active': $badge_class = 'badge-success'; break;
                            case 'pending': $badge_class = 'badge-warning'; break;
                            case 'completed': $badge_class = 'badge-info'; break;
                            case 'voided': $badge_class = 'badge-danger'; break;
                            case 'expired': $badge_class = 'badge-secondary'; break;
                        }
                    ?>
                        <div class="mobile-card">
                            <div class="mobile-card-header">
                                <strong><?php echo htmlspecialchars($referral['referral_num']); ?></strong>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($referral['status']); ?></span>
                            </div>
                            <div class="mobile-card-body">
                                <div class="mobile-card-field">
                                    <span class="mobile-card-label">Patient:</span>
                                    <?php echo htmlspecialchars($patient_name); ?> (<?php echo htmlspecialchars($referral['patient_number']); ?>)
                                </div>
                                <div class="mobile-card-field">
                                    <span class="mobile-card-label">Barangay:</span>
                                    <?php echo htmlspecialchars($referral['barangay'] ?? 'N/A'); ?>
                                </div>
                                <div class="mobile-card-field">
                                    <span class="mobile-card-label">Complaint:</span>
                                    <?php echo htmlspecialchars($referral['chief_complaint']); ?>
                                </div>
                                <div class="mobile-card-field">
                                    <span class="mobile-card-label">Destination:</span>
                                    <?php echo htmlspecialchars($destination); ?>
                                </div>
                                <div class="mobile-card-field">
                                    <span class="mobile-card-label">Date:</span>
                                    <?php echo date('M j, Y g:i A', strtotime($referral['date_of_referral'])); ?>
                                </div>
                                <div class="mobile-card-field">
                                    <span class="mobile-card-label">Issued By:</span>
                                    <?php echo htmlspecialchars($issuer_name); ?>
                                </div>
                                <div class="actions-group" style="margin-top: 0.75rem;">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="viewReferral(<?php echo $referral['id']; ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <?php if ($referral['status'] === 'voided'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="reactivate">
                                            <input type="hidden" name="referral_id" value="<?php echo $referral['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Reactivate this referral?')">
                                                <i class="fas fa-redo"></i> Reactivate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Void Referral Modal -->
    <div id="voidModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Void Referral</h3>
                <button type="button" class="close" onclick="closeModal('voidModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="void">
                <input type="hidden" name="referral_id" id="void_referral_id">

                <div class="form-group">
                    <label for="void_reason">Reason for Voiding *</label>
                    <textarea id="void_reason" name="void_reason" rows="3" required
                        placeholder="Please explain why this referral is being voided..."
                        style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                </div>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('voidModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Void Referral</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Referral Modal -->
    <div id="viewReferralModal" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3><i class="fas fa-share"></i> Referral Details</h3>
                <button type="button" class="close" onclick="closeModal('viewReferralModal')">&times;</button>
            </div>
            
            <div id="referralDetailsContent">
                <!-- Content will be loaded via AJAX -->
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading referral details...</p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewReferralModal')">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-warning" onclick="editReferral()" id="editReferralBtn">
                    <i class="fas fa-edit"></i> Edit Referral
                </button>
                <button type="button" class="btn btn-danger" onclick="cancelReferral()" id="cancelReferralBtn">
                    <i class="fas fa-ban"></i> Cancel Referral
                </button>
                <button type="button" class="btn btn-success" onclick="markComplete()" id="markCompleteBtn">
                    <i class="fas fa-check"></i> Mark Complete
                </button>
                <button type="button" class="btn btn-primary" onclick="printReferral()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Employee Password Verification Modal -->
    <div id="passwordVerificationModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-lock"></i> Employee Verification</h3>
                <button type="button" class="close" onclick="closeModal('passwordVerificationModal')">&times;</button>
            </div>
            <form id="passwordVerificationForm">
                <div class="form-group">
                    <label for="employee_password">Enter your password to proceed:</label>
                    <input type="password" id="employee_password" name="employee_password" required
                        placeholder="Your employee password"
                        style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;">
                </div>
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('passwordVerificationModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Verify & Proceed</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentReferralId = null;
        let pendingAction = null;

        function voidReferral(referralId) {
            document.getElementById('void_referral_id').value = referralId;
            document.getElementById('voidModal').style.display = 'block';
        }

        function viewReferral(referralId) {
            currentReferralId = referralId;
            document.getElementById('viewReferralModal').style.display = 'block';
            
            // Load referral details via AJAX
            fetch(`get_referral_details.php?id=${referralId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayReferralDetails(data);
                    } else {
                        document.getElementById('referralDetailsContent').innerHTML = `
                            <div style="text-align: center; padding: 2rem; color: #dc3545;">
                                <i class="fas fa-exclamation-circle fa-2x"></i>
                                <p>Error loading referral details: ${data.error || 'Unknown error'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('referralDetailsContent').innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: #dc3545;">
                            <i class="fas fa-exclamation-circle fa-2x"></i>
                            <p>Error loading referral details: ${error.message}</p>
                        </div>
                    `;
                });
        }

        function displayReferralDetails(data) {
            const referral = data.referral;
            const vitals = data.vitals;
            const patientAge = data.patient_age;

            const patient_name = `${referral.first_name} ${referral.middle_name ? referral.middle_name + ' ' : ''}${referral.last_name}`;
            const issuer_name = `${referral.issuer_first_name} ${referral.issuer_last_name}`;
            const destination = referral.destination_type === 'external' ? referral.referred_to_external : 'Internal Facility';

            // Determine status badge class
            let statusBadgeClass = 'badge-secondary';
            switch (referral.status) {
                case 'active': statusBadgeClass = 'badge-success'; break;
                case 'pending': statusBadgeClass = 'badge-warning'; break;
                case 'completed': statusBadgeClass = 'badge-info'; break;
                case 'voided': statusBadgeClass = 'badge-danger'; break;
            }

            // Format vitals table
            let vitalsTable = '';
            if (vitals && vitals.length > 0) {
                vitalsTable = `
                    <table class="vitals-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>BP</th>
                                <th>HR</th>
                                <th>RR</th>
                                <th>Temp</th>
                                <th>Weight</th>
                                <th>Height</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                vitals.forEach(vital => {
                    const date = new Date(vital.date_recorded).toLocaleDateString();
                    vitalsTable += `
                        <tr>
                            <td>${date}</td>
                            <td>${vital.blood_pressure || 'N/A'}</td>
                            <td>${vital.heart_rate || 'N/A'}</td>
                            <td>${vital.respiratory_rate || 'N/A'}</td>
                            <td>${vital.temperature || 'N/A'}</td>
                            <td>${vital.weight || 'N/A'}</td>
                            <td>${vital.height || 'N/A'}</td>
                        </tr>
                    `;
                });
                
                vitalsTable += '</tbody></table>';
            } else {
                vitalsTable = '<p style="color: #6c757d; font-style: italic;">No vital signs recorded</p>';
            }

            const content = `
                <div class="referral-details-grid">
                    <!-- Referral Information -->
                    <div class="details-section">
                        <h4><i class="fas fa-share"></i> Referral Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Referral #:</span>
                            <span class="detail-value"><strong>${referral.referral_num}</strong></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="badge ${statusBadgeClass}">${referral.status.charAt(0).toUpperCase() + referral.status.slice(1)}</span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date Issued:</span>
                            <span class="detail-value">${new Date(referral.date_of_referral).toLocaleString()}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Issued By:</span>
                            <span class="detail-value">${issuer_name} (${referral.issuer_position || 'N/A'})</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Destination:</span>
                            <span class="detail-value">${destination}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Priority:</span>
                            <span class="detail-value">${referral.priority || 'Normal'}</span>
                        </div>
                    </div>

                    <!-- Patient Information -->
                    <div class="details-section">
                        <h4><i class="fas fa-user"></i> Patient Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><strong>${patient_name}</strong></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Patient ID:</span>
                            <span class="detail-value">${referral.patient_number}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Age:</span>
                            <span class="detail-value">${patientAge || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Gender:</span>
                            <span class="detail-value">${referral.sex || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Barangay:</span>
                            <span class="detail-value">${referral.barangay || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">${referral.contact_num || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Address:</span>
                            <span class="detail-value">${referral.address || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Civil Status:</span>
                            <span class="detail-value">${referral.civil_status || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Occupation:</span>
                            <span class="detail-value">${referral.occupation || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Emergency Contact:</span>
                            <span class="detail-value">
                                ${referral.emergency_contact_first_name && referral.emergency_contact_last_name 
                                    ? `${referral.emergency_contact_first_name} ${referral.emergency_contact_last_name}` 
                                    : 'N/A'}
                                ${referral.emergency_contact_relation ? ` (${referral.emergency_contact_relation})` : ''}
                                ${referral.emergency_contact_number ? `<br><small>${referral.emergency_contact_number}</small>` : ''}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="details-section" style="margin-bottom: 1.5rem;">
                    <h4><i class="fas fa-stethoscope"></i> Medical Information</h4>
                    <div class="detail-item">
                        <span class="detail-label">Chief Complaint:</span>
                        <span class="detail-value">${referral.chief_complaint || 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">History of Present Illness:</span>
                        <span class="detail-value">${referral.history_of_present_illness || 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Past Medical History:</span>
                        <span class="detail-value">${referral.past_medical_history || 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Physical Examination:</span>
                        <span class="detail-value">${referral.physical_examination || 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Diagnosis:</span>
                        <span class="detail-value">${referral.diagnosis || 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reason for Referral:</span>
                        <span class="detail-value">${referral.reason_for_referral || 'N/A'}</span>
                    </div>
                </div>

                <!-- Recent Vital Signs -->
                <div class="details-section" style="margin-bottom: 1.5rem;">
                    <h4><i class="fas fa-heartbeat"></i> Recent Vital Signs</h4>
                    ${vitalsTable}
                </div>
            `;

            document.getElementById('referralDetailsContent').innerHTML = content;

            // Update modal footer buttons based on status
            updateModalButtons(referral.status);
        }

        function updateModalButtons(status) {
            const editBtn = document.getElementById('editReferralBtn');
            const cancelBtn = document.getElementById('cancelReferralBtn');
            const completeBtn = document.getElementById('markCompleteBtn');

            // Hide/show buttons based on status
            if (status === 'voided' || status === 'completed') {
                editBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
                completeBtn.style.display = 'none';
            } else {
                editBtn.style.display = 'inline-flex';
                cancelBtn.style.display = 'inline-flex';
                completeBtn.style.display = 'inline-flex';
            }
        }

        function editReferral() {
            pendingAction = 'edit';
            document.getElementById('passwordVerificationModal').style.display = 'block';
        }

        function cancelReferral() {
            pendingAction = 'cancel';
            document.getElementById('passwordVerificationModal').style.display = 'block';
        }

        function markComplete() {
            if (confirm('Mark this referral as completed?')) {
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="referral_id" value="${currentReferralId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function printReferral() {
            if (currentReferralId) {
                window.open(`print_referral.php?id=${currentReferralId}`, '_blank');
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            
            // Clear modal contents and reset variables
            if (modalId === 'viewReferralModal') {
                currentReferralId = null;
                document.getElementById('referralDetailsContent').innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Loading referral details...</p>
                    </div>
                `;
            }
            
            if (modalId === 'voidModal') {
                document.getElementById('void_reason').value = '';
            }
            
            if (modalId === 'passwordVerificationModal') {
                document.getElementById('employee_password').value = '';
                pendingAction = null;
            }
        }

        // Password verification form handler
        document.getElementById('passwordVerificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('employee_password').value;
            
            // Verify password via AJAX
            fetch('verify_employee_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `password=${encodeURIComponent(password)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('passwordVerificationModal');
                    
                    if (pendingAction === 'edit') {
                        window.location.href = `create_referral.php?edit=${currentReferralId}`;
                    } else if (pendingAction === 'cancel') {
                        if (confirm('Are you sure you want to cancel this referral? This action cannot be undone.')) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="void">
                                <input type="hidden" name="referral_id" value="${currentReferralId}">
                                <input type="hidden" name="void_reason" value="Cancelled by ${data.employee_name}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    }
                } else {
                    alert('Invalid password. Please try again.');
                }
            })
            .catch(error => {
                alert('Error verifying password. Please try again.');
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Handle responsive view switching
        function handleResponsiveView() {
            const tableWrapper = document.querySelector('.table-wrapper');
            const mobileCards = document.querySelector('.mobile-cards');
            const width = window.innerWidth;

            if (width <= 400) {
                // Very small screens - show mobile cards
                if (tableWrapper) tableWrapper.style.display = 'none';
                if (mobileCards) mobileCards.style.display = 'block';
            } else {
                // Larger screens - show table
                if (tableWrapper) tableWrapper.style.display = 'block';
                if (mobileCards) mobileCards.style.display = 'none';
            }
        }

        // Initialize responsive view on load
        document.addEventListener('DOMContentLoaded', function() {
            handleResponsiveView();
            
            // Search form optimization
            const searchForm = document.getElementById('search-form');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    // Remove empty search fields to clean up URL
                    const inputs = this.querySelectorAll('input[type="text"], select');
                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            input.name = '';
                        }
                    });
                });
            }
        });
        
        // Handle resize
        window.addEventListener('resize', handleResponsiveView);
    </script>
</body>

</html>