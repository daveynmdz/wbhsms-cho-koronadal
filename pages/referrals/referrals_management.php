<?php
// referrals_management.php - Admin Side
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Check if role is authorized
$authorized_roles = ['doctor', 'bhw', 'dho', 'records_officer', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: ../management/' . strtolower($_SESSION['role']) . '/dashboard.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

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
                    $stmt = $conn->prepare("UPDATE referrals SET status = 'completed' WHERE referral_id = ?");
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
                        $stmt = $conn->prepare("UPDATE referrals SET status = 'voided' WHERE referral_id = ?");
                        $stmt->bind_param("i", $referral_id);
                        $stmt->execute();
                        $message = "Referral voided successfully.";
                        $stmt->close();
                    }
                    break;

                case 'reactivate':
                    $stmt = $conn->prepare("UPDATE referrals SET status = 'active' WHERE referral_id = ?");
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

// Pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = in_array(intval($_GET['per_page'] ?? 25), [10, 25, 50, 100]) ? intval($_GET['per_page'] ?? 25) : 25;
$offset = ($page - 1) * $per_page;

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
    // Auto-update expired referrals (over 48 hours old and still active/pending)
    $expire_stmt = $conn->prepare("
        UPDATE referrals 
        SET status = 'cancelled' 
        WHERE status IN ('active', 'pending') 
        AND TIMESTAMPDIFF(HOUR, referral_date, NOW()) > 48
    ");
    $expire_stmt->execute();
    $expire_stmt->close();

    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN employees e ON r.referred_by = e.employee_id
        LEFT JOIN facilities f ON r.referred_to_facility_id = f.facility_id
        $where_clause
    ";

    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = ceil($total_records / $per_page);

    $sql = "
        SELECT r.referral_id, r.referral_num, r.patient_id, r.referral_reason, r.destination_type, 
               r.referred_to_facility_id, r.external_facility_name, r.referral_date, r.status,
               p.first_name, p.middle_name, p.last_name, p.username as patient_number, 
               b.barangay_name as barangay,
               e.first_name as issuer_first_name, e.last_name as issuer_last_name,
               f.name as referred_facility_name
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN employees e ON r.referred_by = e.employee_id
        LEFT JOIN facilities f ON r.referred_to_facility_id = f.facility_id
        $where_clause
        ORDER BY r.referral_date DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    // Add pagination parameters
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';

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
    $total_records = 0;
    $total_pages = 0;
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
    <!-- CSS Files - loaded by sidebar -->
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

        .card-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
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
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
            /* Ensures table doesn't get too compressed */
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            white-space: normal;
            align-content: flex-start;
        }

        .table th {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 10;
            cursor: pointer;
            user-select: none;
            align-content: center;
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
            .table th:nth-child(3),
            /* Barangay */
            .table td:nth-child(3),
            .table th:nth-child(6),
            /* Status */
            .table td:nth-child(6),
            .table th:nth-child(8),
            /* Issued By */
            .table td:nth-child(8) {
                display: none;
            }

            /* Adjust remaining columns */
            .table th:nth-child(2),
            /* Patient */
            .table td:nth-child(2) {
                min-width: 120px;
            }

            .table th:nth-child(4),
            /* Chief Complaint */
            .table td:nth-child(4) {
                min-width: 150px;
                white-space: normal;
                word-wrap: break-word;
            }

            .table th:nth-child(7),
            /* Issued Date */
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

        /* Higher z-index for cancel modal to appear on top of view modal */
        #cancelReferralModal {
            z-index: 11000;
        }

        /* Higher z-index for reinstate modal to appear on top of view modal */
        #reinstateReferralModal {
            z-index: 11000;
        }

        /* Higher z-index for password verification modal */
        #passwordVerificationModal {
            z-index: 12000;
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

        /* Referral Details Modal Styles (matching create_referrals.php) */
        .referral-confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        }

        .referral-confirmation-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .referral-modal-content {
            background: white;
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideInUp 0.4s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .referral-modal-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            text-align: center;
            position: relative;
            flex-shrink: 0;
        }

        .referral-modal-header h3 {
            margin: 0;
            font-size: 1.5em;
            font-weight: 600;
        }

        .referral-modal-header .icon {
            font-size: 3em;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .referral-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2em;
            transition: background 0.3s;
        }

        .referral-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .referral-modal-body {
            padding: 2rem;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .referral-modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .referral-modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .referral-modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .referral-modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .referral-summary-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .summary-section {
            margin-bottom: 1.5rem;
        }

        .summary-section:last-child {
            margin-bottom: 0;
        }

        .summary-title {
            font-weight: 700;
            color: #0077b6;
            font-size: 1.1em;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .summary-title i {
            background: #e3f2fd;
            padding: 0.5rem;
            border-radius: 8px;
            color: #0077b6;
            font-size: 0.9em;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .summary-label {
            font-size: 0.85em;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 1.05em;
            color: #333;
            font-weight: 500;
            word-wrap: break-word;
        }

        .summary-value.highlight {
            color: #0077b6;
            font-weight: 600;
        }

        .summary-value.reason {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #0077b6;
            margin-top: 0.5rem;
            line-height: 1.5;
        }

        .vitals-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .vital-item {
            background: white;
            padding: 0.8rem;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e9ecef;
        }

        .vital-value {
            font-size: 1.2em;
            font-weight: 700;
            color: #0077b6;
        }

        .vital-label {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 0.3rem;
        }

        .referral-modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            padding: 1rem 1.5rem 1.5rem;
            flex-shrink: 0;
            background: white;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        .modal-btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
            min-width: 90px;
            flex: 1;
            max-width: 120px;
            gap: 20px;
            align-items: center;
        }

        .modal-btn-secondary {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #dee2e6;
        }

        .modal-btn-secondary:hover {
            background: #e9ecef;
            color: #5a6268;
        }

        .modal-btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 119, 182, 0.3);
        }

        .modal-btn-primary:hover {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 119, 182, 0.4);
        }

        .modal-btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .modal-btn-success:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .modal-btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .modal-btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .modal-btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .modal-btn-warning:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }

        /* Mobile responsive design for modal */
        @media (max-width: 768px) {
            .referral-modal-actions {
                gap: 0.5rem;
                padding: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }

            .modal-btn {
                padding: 0.5rem 0.8rem;
                font-size: 0.8em;
                min-width: 80px;
                max-width: 120px;
                flex: 1 1 auto;
            }

            .referral-confirmation-modal .modal-content {
                margin: 0.5rem;
                max-height: 95vh;
            }

            .modal-header h3 {
                font-size: 1.2em;
            }

            .modal-body {
                font-size: 0.9em;
                max-height: calc(95vh - 200px);
            }
        }

        @media (max-width: 480px) {
            .referral-modal-actions {
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }

            .modal-btn {
                max-width: 100%;
                padding: 0.75rem 1rem;
                font-size: 0.85em;
                flex: none;
                width: 100%;
                gap: 20px;
                align-items: center;
            }
        }

        @media (max-width: 360px) {
            .referral-modal-actions {
                padding: 0.75rem;
            }

            .modal-btn {
                padding: 0.6rem 0.8rem;
                font-size: 0.8em;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideInUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Snackbar animations */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }

            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        @media (max-width: 768px) {
            .referral-modal-content {
                margin: 1rem;
                border-radius: 15px;
            }

            .referral-modal-header {
                padding: 1.5rem;
                border-radius: 15px 15px 0 0;
            }

            .referral-modal-header h3 {
                font-size: 1.3em;
            }

            .referral-modal-header .icon {
                font-size: 2.5em;
            }

            .referral-modal-body {
                padding: 1.5rem;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .vitals-summary {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            }

            .referral-modal-actions {
                flex-direction: column;
                padding: 1rem 1.5rem 1.5rem;
            }

            .modal-btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .modal-btn:last-child {
                margin-bottom: 0;
            }
        }

        /* Pagination Styles */
        .pagination-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .pagination-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .records-info {
            color: #666;
            font-size: 0.9rem;
        }

        .records-info strong {
            color: #0077b6;
        }

        .page-size-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-size-selector label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        .page-size-selector select {
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }

        .page-size-selector select:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            background: white;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            min-width: 40px;
            justify-content: center;
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            border-color: #0077b6;
            color: #0077b6;
            background: #f8f9fa;
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            border-color: #0077b6;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            color: #ccc;
        }

        .pagination-btn.prev,
        .pagination-btn.next {
            padding: 0.5rem 1rem;
        }

        .pagination-ellipsis {
            padding: 0.5rem;
            color: #666;
        }

        /* Mobile responsive pagination */
        @media (max-width: 768px) {
            .pagination-info {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .pagination-controls {
                gap: 0.25rem;
            }

            .pagination-btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
                min-width: 35px;
            }

            .pagination-btn.prev,
            .pagination-btn.next {
                padding: 0.4rem 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .pagination-container {
                padding: 1rem;
            }

            .pagination-controls {
                justify-content: center;
            }

            .pagination-btn {
                padding: 0.35rem 0.5rem;
                font-size: 0.75rem;
                min-width: 32px;
            }

            /* Hide some page numbers on very small screens */
            .pagination-btn.page-num:not(.active):nth-child(n+6) {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php
    // Include dynamic sidebar helper
    require_once $root_path . '/includes/dynamic_sidebar_helper.php';

    // Include the correct sidebar based on user role
    includeDynamicSidebar('referrals', $root_path);
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="<?php echo getRoleDashboardUrl(); ?>"><i class="fas fa-home"></i> Dashboard</a>
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
            <div class="stat-card canceled">
                <div class="stat-number"><?php echo number_format($stats['canceled'] ?? 0); ?></div>
                <div class="stat-label">Canceled</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <div class="section-header" style="padding: 0 0 15px 0;margin-bottom: 15px;border-bottom: 1px solid rgba(0, 119, 182, 0.2);">
                <h4 style="margin: 0;color: var(--primary-dark);font-size: 18px;font-weight: 600;">
                    <i class="fas fa-filter"></i> Search & Filter Options
                </h4>
            </div>
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
                        <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="issued" <?php echo $status_filter === 'issued' ? 'selected' : ''; ?>>Issued</option>
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
        <div class="card-container">
            <div class="section-header" style="padding: 0 0 15px 0;margin-bottom: 15px;border-bottom: 1px solid rgba(0, 119, 182, 0.2);">
                <h4 style="margin: 0;color: var(--primary-dark);font-size: 18px;font-weight: 600;">
                    <i class="fas fa-table"></i> Referrals Issued
                </h4>
            </div>
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
                                    <th>Reason for Referral</th>
                                    <th>Referred Facility</th>
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

                                    // Determine destination based on destination_type
                                    if ($referral['destination_type'] === 'external') {
                                        $destination = $referral['external_facility_name'] ?: 'External Facility';
                                    } else {
                                        $destination = $referral['referred_facility_name'] ?: 'Internal Facility';
                                    }

                                    // Determine badge class based on status
                                    $badge_class = 'badge-secondary';
                                    switch ($referral['status']) {
                                        case 'active':
                                            $badge_class = 'badge-success';
                                            break;
                                        case 'accepted':
                                            $badge_class = 'badge-info';
                                            break;
                                        case 'completed':
                                            $badge_class = 'badge-primary';
                                            break;
                                        case 'cancelled':
                                            $badge_class = 'badge-danger';
                                            break;
                                        default:
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
                                                <?php echo htmlspecialchars($referral['referral_reason']); ?>
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
                                                <?php echo date('M j, Y', strtotime($referral['referral_date'])); ?>
                                                <br><small><?php echo date('g:i A', strtotime($referral['referral_date'])); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($issuer_name); ?></td>
                                        <td>
                                            <div class="actions-group">
                                                <button type="button" class="btn btn-primary btn-sm" onclick="viewReferral(<?php echo $referral['referral_id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($referral['status'] === 'voided'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="reactivate">
                                                        <input type="hidden" name="referral_id" value="<?php echo $referral['referral_id']; ?>">
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

                            // Determine destination based on destination_type
                            if ($referral['destination_type'] === 'external') {
                                $destination = $referral['external_facility_name'] ?: 'External Facility';
                            } else {
                                $destination = $referral['referred_facility_name'] ?: 'Internal Facility';
                            }

                            // Determine badge class based on status
                            $badge_class = 'badge-secondary';
                            switch ($referral['status']) {
                                case 'active':
                                    $badge_class = 'badge-success';
                                    break;
                                case 'accepted':
                                    $badge_class = 'badge-info';
                                    break;
                                case 'completed':
                                    $badge_class = 'badge-primary';
                                    break;
                                case 'cancelled':
                                    $badge_class = 'badge-danger';
                                    break;
                                default:
                                    $badge_class = 'badge-secondary';
                                    break;
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
                                        <span class="mobile-card-label">Referral Reason:</span>
                                        <?php echo htmlspecialchars($referral['referral_reason']); ?>
                                    </div>
                                    <div class="mobile-card-field">
                                        <span class="mobile-card-label">Destination:</span>
                                        <?php echo htmlspecialchars($destination); ?>
                                    </div>
                                    <div class="mobile-card-field">
                                        <span class="mobile-card-label">Date:</span>
                                        <?php echo date('M j, Y g:i A', strtotime($referral['referral_date'])); ?>
                                    </div>
                                    <div class="mobile-card-field">
                                        <span class="mobile-card-label">Issued By:</span>
                                        <?php echo htmlspecialchars($issuer_name); ?>
                                    </div>
                                    <div class="actions-group" style="margin-top: 0.75rem;">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="viewReferral(<?php echo $referral['referral_id']; ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <?php if ($referral['status'] === 'cancelled'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="reactivate">
                                                <input type="hidden" name="referral_id" value="<?php echo $referral['referral_id']; ?>">
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

                <!-- Pagination Container -->
                <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <div class="records-info">
                                Showing <strong><?php echo (($page - 1) * $per_page) + 1; ?></strong> to
                                <strong><?php echo min($page * $per_page, $total_records); ?></strong> of
                                <strong><?php echo $total_records; ?></strong> referrals
                            </div>

                            <div class="page-size-selector">
                                <label for="perPageSelect">Show:</label>
                                <select id="perPageSelect" onchange="changePageSize(this.value)">
                                    <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-controls">
                                <!-- Previous button -->
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                        class="pagination-btn prev">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-btn prev disabled">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </span>
                                <?php endif; ?>

                                <?php
                                // Calculate page numbers to show
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                // Show first page if not in range
                                if ($start_page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                        class="pagination-btn page-num">1</a>
                                    <?php if ($start_page > 2): ?>
                                        <span class="pagination-ellipsis">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Page numbers -->
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="pagination-btn active page-num"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                            class="pagination-btn page-num"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Show last page if not in range -->
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span class="pagination-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                        class="pagination-btn page-num"><?php echo $total_pages; ?></a>
                                <?php endif; ?>

                                <!-- Next button -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                        class="pagination-btn next">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-btn next disabled">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
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
    <div id="viewReferralModal" class="referral-confirmation-modal">
        <div class="referral-modal-content">
            <div class="referral-modal-header">
                <button type="button" class="referral-modal-close" onclick="closeReferralModal()">&times;</button>
                <div class="icon">
                    <i class="fas fa-eye"></i>
                </div>
                <h3>Referral Details</h3>
                <p style="margin: 0.5rem 0 0; opacity: 0.9;">Complete information about this referral</p>
            </div>

            <div class="referral-modal-body">
                <div id="referralDetailsContent">
                    <!-- Content will be loaded via JavaScript -->
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p>Loading referral details...</p>
                    </div>
                </div>
            </div>

            <div class="referral-modal-actions">
                <!-- Edit Button - Show for Active status -->
                <button type="button" class="modal-btn modal-btn-warning" onclick="editReferral()" id="editReferralBtn" style="display: none;">
                    <i class="fas fa-edit"></i> Edit
                </button>

                <!-- Cancel Button - Show for Active status -->
                <button type="button" class="modal-btn modal-btn-danger" onclick="cancelReferral()" id="cancelReferralBtn" style="display: none;">
                    <i class="fas fa-times-circle"></i> Cancel Referral
                </button>

                <!-- Reinstate Button - Show for Cancelled/Expired status -->
                <button type="button" class="modal-btn modal-btn-success" onclick="reinstateReferral()" id="reinstateReferralBtn" style="display: none;">
                    <i class="fas fa-redo"></i> Reinstate
                </button>

                <!-- Print Button - Always available -->
                <button type="button" class="modal-btn modal-btn-primary" onclick="printReferral()" id="printReferralBtn">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Cancel Referral Modal -->
    <div id="cancelReferralModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle text-danger"></i> Cancel Referral</h3>
                <button type="button" class="close" onclick="closeModal('cancelReferralModal')">&times;</button>
            </div>
            <form id="cancelReferralForm">
                <div class="alert" style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeaa7; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will cancel the referral and notify all involved parties. This action can be undone later using the "Reinstate" option.
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="cancel_reason"><strong>Reason for Cancellation *</strong></label>
                    <textarea id="cancel_reason" name="cancel_reason" rows="4" required
                        placeholder="Please provide a detailed reason for cancelling this referral (minimum 10 characters)..."
                        style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; resize: vertical; min-height: 100px;"></textarea>
                    <small style="color: #666; font-size: 0.85em;">This reason will be logged and visible in the referral history.</small>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="cancel_employee_password"><strong>Your Password *</strong></label>
                    <input type="password" id="cancel_employee_password" name="employee_password" required
                        placeholder="Enter your employee password to confirm cancellation"
                        style="width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <small style="color: #666; font-size: 0.85em;">Password verification is required for security purposes.</small>
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cancelReferralModal')" style="min-width: 100px;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" style="min-width: 140px;">
                        <i class="fas fa-times-circle"></i> Cancel Referral
                    </button>
                </div>
            </form>
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

    <!-- Reinstate Referral Confirmation Modal -->
    <div id="reinstateReferralModal" class="modal">
        <div class="modal-content" style="max-width: 500px;text-align: left;">
            <div class="modal-header">
                <h3><i class="fas fa-undo-alt" style="color: #28a745;"></i> Reinstate Referral</h3>
                <button type="button" class="close" onclick="closeModal('reinstateReferralModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <h4 style="color: #155724; margin: 0 0 0.5rem 0; font-size: 1rem;">
                        <i class="fas fa-info-circle"></i> Confirmation Required
                    </h4>
                    <p style="margin: 0; color: #155724; line-height: 1.5;">Are you sure you want to reinstate this referral?</p>
                </div>

                <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <h5 style="color: #0077b6; margin: 0 0 0.75rem 0; font-size: 0.95rem;">
                        <i class="fas fa-list-ul"></i> This action will:
                    </h5>
                    <ul style="margin: 0; padding-left: 1.2rem; color: #555;">
                        <li style="margin-bottom: 0.5rem;">Reactivate the referral status to <strong>"Active"</strong></li>
                        <li style="margin-bottom: 0.5rem;">Make it available for processing again</li>
                        <li style="margin-bottom: 0.5rem;">Log the reinstatement action for audit purposes</li>
                        <li style="margin: 0;">Send notification to relevant healthcare providers</li>
                    </ul>
                </div>

                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 1rem;">
                    <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                        <i class="fas fa-exclamation-triangle" style="color: #f39c12;"></i>
                        <strong>Note:</strong> Once reinstated, this referral will become active and ready for patient processing.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reinstateReferralModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" onclick="confirmReinstatement()" id="confirmReinstateBtn">
                    <i class="fas fa-undo-alt"></i> Yes, Reinstate Referral
                </button>
            </div>
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

            // Show modal with new styling
            const modal = document.getElementById('viewReferralModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

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

            // Determine destination based on destination_type
            let destination = '';
            if (referral.destination_type === 'external') {
                destination = referral.external_facility_name || referral.referred_to_external || 'External Facility';
            } else {
                destination = referral.referred_facility_name || 'Internal Facility';
            }

            // Format vitals for display
            let vitalsContent = '';
            let hasVitals = false;

            if (vitals && vitals.length > 0) {
                const latestVital = vitals[0]; // Assuming vitals are sorted by date DESC
                vitalsContent = '<div class="vitals-summary">';

                if (latestVital.blood_pressure) {
                    vitalsContent += `<div class="vital-item"><div class="vital-value">${latestVital.blood_pressure}</div><div class="vital-label">Blood Pressure</div></div>`;
                    hasVitals = true;
                }
                if (latestVital.heart_rate) {
                    vitalsContent += `<div class="vital-item"><div class="vital-value">${latestVital.heart_rate}</div><div class="vital-label">Heart Rate (bpm)</div></div>`;
                    hasVitals = true;
                }
                if (latestVital.respiratory_rate) {
                    vitalsContent += `<div class="vital-item"><div class="vital-value">${latestVital.respiratory_rate}</div><div class="vital-label">Resp Rate (/min)</div></div>`;
                    hasVitals = true;
                }
                if (latestVital.temperature) {
                    vitalsContent += `<div class="vital-item"><div class="vital-value">${latestVital.temperature}</div><div class="vital-label">Temperature (°C)</div></div>`;
                    hasVitals = true;
                }
                if (latestVital.weight) {
                    vitalsContent += `<div class="vital-item"><div class="vital-value">${latestVital.weight}</div><div class="vital-label">Weight (kg)</div></div>`;
                    hasVitals = true;
                }
                if (latestVital.height) {
                    vitalsContent += `<div class="vital-item"><div class="vital-value">${latestVital.height}</div><div class="vital-label">Height (cm)</div></div>`;
                    hasVitals = true;
                }

                vitalsContent += '</div>';

                if (latestVital.remarks) {
                    vitalsContent += `
                        <div class="summary-item" style="margin-top: 1rem;">
                            <div class="summary-label">Vitals Remarks</div>
                            <div class="summary-value">${latestVital.remarks}</div>
                        </div>
                    `;
                    hasVitals = true;
                }
            }

            const content = `
                <!-- Patient Information -->
                <div class="referral-summary-card">
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="fas fa-user"></i>
                            Patient Information
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Patient Name</div>
                                <div class="summary-value highlight">${patient_name}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Patient ID</div>
                                <div class="summary-value">${referral.patient_number || 'N/A'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Age</div>
                                <div class="summary-value">${patientAge || 'N/A'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Gender</div>
                                <div class="summary-value">${referral.sex || 'N/A'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Barangay</div>
                                <div class="summary-value">${referral.barangay || 'N/A'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Contact Number</div>
                                <div class="summary-value">${referral.contact_number || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Referral Details -->
                <div class="referral-summary-card">
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="fas fa-share"></i>
                            Referral Details
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Referral Number</div>
                                <div class="summary-value highlight">${referral.referral_num || 'N/A'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Status</div>
                                <div class="summary-value">
                                    <span class="badge badge-${referral.status === 'active' ? 'success' : referral.status === 'completed' ? 'primary' : referral.status === 'accepted' ? 'info' : referral.status === 'cancelled' ? 'danger' : 'secondary'}">
                                        ${referral.status ? referral.status.charAt(0).toUpperCase() + referral.status.slice(1) : 'Unknown'}
                                    </span>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Issued By</div>
                                <div class="summary-value">${issuer_name}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Date Issued</div>
                                <div class="summary-value">${referral.referral_date ? new Date(referral.referral_date).toLocaleDateString() : 'N/A'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Destination</div>
                                <div class="summary-value highlight">${destination}</div>
                            </div>
                            ${referral.service_name ? `
                            <div class="summary-item">
                                <div class="summary-label">Service Requested</div>
                                <div class="summary-value">${referral.service_name}</div>
                            </div>
                            ` : ''}
                        </div>
                        <div class="summary-item" style="margin-top: 1rem;">
                            <div class="summary-label">Reason for Referral</div>
                            <div class="summary-value reason">${referral.referral_reason || referral.reason_for_referral || 'No reason specified'}</div>
                        </div>
                    </div>
                </div>

                ${hasVitals ? `
                <!-- Patient Vitals -->
                <div class="referral-summary-card">
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="fas fa-heartbeat"></i>
                            Recent Vital Signs
                        </div>
                        ${vitalsContent}
                    </div>
                </div>
                ` : ''}
            `;

            document.getElementById('referralDetailsContent').innerHTML = content;

            // Update modal footer buttons based on status
            updateModalButtons(referral.status);
        }

        function updateModalButtons(status) {
            const editBtn = document.getElementById('editReferralBtn');
            const cancelBtn = document.getElementById('cancelReferralBtn');
            const reinstateBtn = document.getElementById('reinstateReferralBtn');
            const printBtn = document.getElementById('printReferralBtn');

            // Hide all buttons initially
            editBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
            reinstateBtn.style.display = 'none';

            // Show buttons based on status
            switch (status.toLowerCase()) {
                case 'active':
                    // Active: Show Edit, Cancel Referral, Print
                    editBtn.style.display = 'inline-flex';
                    cancelBtn.style.display = 'inline-flex';
                    break;

                case 'cancelled':
                case 'expired':
                    // Cancelled/Expired: Show Reinstate, Print (only for these specific statuses)
                    reinstateBtn.style.display = 'inline-flex';
                    break;

                case 'voided':
                    // Voided: Show Print only (voided referrals cannot be reinstated)
                    break;

                case 'accepted':
                case 'issued':
                case 'completed':
                    // Accepted/Issued/Completed: Show Print only (status updated automatically)
                    // Print button is always visible, so no additional action needed
                    break;

                default:
                    // For any other status, show only Print
                    break;
            }

            // Print button is always visible
            printBtn.style.display = 'inline-flex';
        }

        function editReferral() {
            if (!currentReferralId) {
                console.warn('No referral ID available for editing');
                alert('Unable to edit referral: Referral ID not found. Please try again.');
                return;
            }
            pendingAction = 'edit';
            document.getElementById('passwordVerificationModal').style.display = 'block';
        }

        function cancelReferral() {
            if (!currentReferralId) {
                showErrorMessage('Unable to cancel referral: Referral ID not found. Please try again.');
                return;
            }

            // Clear previous form data and show modal
            document.getElementById('cancel_reason').value = '';
            document.getElementById('cancel_employee_password').value = '';
            document.getElementById('cancelReferralModal').style.display = 'block';
        }



        function reinstateReferral() {
            if (!currentReferralId) {
                console.warn('No referral ID available for reinstatement');
                showErrorMessage('Unable to reinstate referral: Referral ID not found. Please try again.');
                return;
            }

            // Show custom confirmation modal
            document.getElementById('reinstateReferralModal').style.display = 'block';
        }

        function confirmReinstatement() {
            if (!currentReferralId) {
                showErrorMessage('Unable to reinstate referral: Referral ID not found.');
                return;
            }

            // Close the confirmation modal
            closeModal('reinstateReferralModal');

            // Disable the reinstate button to prevent double-clicks
            const reinstateBtn = document.getElementById('reinstateReferralBtn');
            const confirmBtn = document.getElementById('confirmReinstateBtn');
            const originalText = reinstateBtn ? reinstateBtn.innerHTML : 'Reinstate';

            if (reinstateBtn) {
                reinstateBtn.disabled = true;
                reinstateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reinstating...';
            }

            // Send AJAX request to reinstate referral
            fetch('reinstate_referral.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `referral_id=${encodeURIComponent(currentReferralId)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI to reflect active status
                        updateReferralStatusInUI('active');

                        // Show success message with snackbar
                        showSuccessMessage('Referral has been successfully reinstated and is now active.');

                        // Optionally refresh the page to show updated data in the table
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);

                    } else {
                        // Show error message with snackbar
                        showErrorMessage(data.message || 'Failed to reinstate referral. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error reinstating referral:', error);
                    showErrorMessage('Error reinstating referral. Please check your connection and try again.');
                })
                .finally(() => {
                    // Re-enable reinstate button
                    if (reinstateBtn) {
                        reinstateBtn.disabled = false;
                        reinstateBtn.innerHTML = originalText;
                    }
                });
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
            if (!currentReferralId) {
                console.warn('No referral ID available for printing');
                alert('Unable to print referral: Referral ID not found. Please try again.');
                return;
            }

            // Get current referral details from the modal
            const referralDetailsContent = document.getElementById('referralDetailsContent');
            if (!referralDetailsContent) {
                alert('Referral details not loaded. Please try again.');
                return;
            }

            // Create print window with professional styling
            const printWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes');

            if (!printWindow) {
                alert('Pop-up blocked. Please allow pop-ups for this site to print referrals.');
                return;
            }

            // Get current date and time for print timestamp
            const printTimestamp = new Date().toLocaleString();

            // Generate print content with professional medical document styling
            const printContent = generatePrintContent(referralDetailsContent.innerHTML, printTimestamp);

            printWindow.document.write(printContent);
            printWindow.document.close();

            // Focus the print window and trigger print dialog
            printWindow.focus();

            // Small delay to ensure content is rendered before printing
            setTimeout(() => {
                printWindow.print();
                // Note: Don't auto-close the window to allow user to review or reprint
                // printWindow.close(); // Uncomment if auto-close is desired
            }, 500);
        }

        // Function to generate professional print content
        function generatePrintContent(modalContent, timestamp) {
            return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Details - CHO Koronadal</title>
    <style>
        /* Print-specific styles for professional medical document */
        @media print {
            @page {
                margin: 1in;
                size: A4;
            }
            
            body {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.6;
            color: #000;
            background: white;
            font-size: 12pt;
        }

        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0077b6;
        }

        .print-header h1 {
            color: #0077b6;
            font-size: 24pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .print-header h2 {
            color: #023e8a;
            font-size: 18pt;
            font-weight: normal;
            margin-bottom: 10px;
        }

        .print-header .header-info {
            font-size: 10pt;
            color: #666;
            margin-top: 10px;
        }

        .referral-summary-card {
            background: transparent !important;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .summary-title {
            font-weight: bold;
            color: #0077b6;
            font-size: 14pt;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 8px;
        }

        .summary-title i {
            background: #e3f2fd;
            padding: 6px;
            border-radius: 4px;
            color: #0077b6;
            font-size: 12pt;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .summary-label {
            font-size: 9pt;
            color: #666;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 11pt;
            color: #000;
            font-weight: normal;
            word-wrap: break-word;
            min-height: 16px;
        }

        .summary-value.highlight {
            color: #0077b6;
            font-weight: bold;
        }

        .summary-value.reason {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            border-left: 3px solid #0077b6;
            margin-top: 8px;
            line-height: 1.5;
            font-style: italic;
        }

        .vitals-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .vital-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .vital-value {
            font-size: 14pt;
            font-weight: bold;
            color: #0077b6;
            display: block;
        }

        .vital-label {
            font-size: 8pt;
            color: #666;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .print-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }

        .print-timestamp {
            margin-top: 10px;
            font-style: italic;
        }

        /* Hide modal-specific elements */
        .modal-btn,
        .referral-modal-actions,
        button {
            display: none !important;
        }

        /* Ensure proper spacing for sections */
        .summary-section {
            margin-bottom: 20px;
        }

        .summary-section:last-child {
            margin-bottom: 0;
        }

        /* Professional table styling for any tabular data */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table th,
        table td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 10pt;
        }

        table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #0077b6;
        }

        /* Page break controls */
        .page-break-before {
            page-break-before: always;
        }

        .page-break-after {
            page-break-after: always;
        }

        .no-page-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <!-- Document Header -->
    <div class="print-header">
        <h1>City Health Office</h1>
        <h2>Koronadal City, South Cotabato</h2>
        <div class="header-info">
            <strong>MEDICAL REFERRAL DOCUMENT</strong><br>
            This is an official medical referral issued by CHO Koronadal
        </div>
    </div>

    <!-- Referral Content -->
    <div class="referral-content">
        ${modalContent}
    </div>

    <!-- Document Footer -->
    <div class="print-footer">
        <div>
            <strong>CHO Koronadal - Health Management System</strong><br>
            For inquiries, contact the City Health Office at [Contact Information]
        </div>
        <div class="print-timestamp">
            Document printed on: ${timestamp}
        </div>
    </div>
</body>
</html>`;
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

            if (modalId === 'cancelReferralModal') {
                document.getElementById('cancel_reason').value = '';
                document.getElementById('cancel_employee_password').value = '';
            }

            if (modalId === 'reinstateReferralModal') {
                // Reset any state if needed
            }
        }

        // Function to close referral modal (new modal style)
        function closeReferralModal() {
            const modal = document.getElementById('viewReferralModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';

            // Clear modal contents and reset variables
            document.getElementById('referralDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading referral details...</p>
                </div>
            `;
            currentReferralId = null;
        }

        // Function to update UI after referral status change
        function updateReferralStatusInUI(newStatus, reason = '') {
            // Update modal buttons based on new status
            updateModalButtons(newStatus);

            // Update status in the referral details if they're currently displayed
            const statusElements = document.querySelectorAll('[data-referral-id="' + currentReferralId + '"] .badge');
            statusElements.forEach(element => {
                // Remove old badge classes
                element.classList.remove('badge-primary', 'badge-success', 'badge-warning', 'badge-danger', 'badge-secondary');

                // Add new badge class and text based on status
                switch (newStatus.toLowerCase()) {
                    case 'cancelled':
                        element.classList.add('badge-danger');
                        element.textContent = 'Cancelled';
                        break;
                    case 'active':
                        element.classList.add('badge-success');
                        element.textContent = 'Active';
                        break;
                    case 'completed':
                        element.classList.add('badge-primary');
                        element.textContent = 'Completed';
                        break;
                    case 'expired':
                        element.classList.add('badge-warning');
                        element.textContent = 'Expired';
                        break;
                    case 'voided':
                        element.classList.add('badge-secondary');
                        element.textContent = 'Voided';
                        break;
                    default:
                        element.classList.add('badge-secondary');
                        element.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                }
            });

            // If we're viewing details, update the status in the modal content
            const modalStatusElements = document.querySelectorAll('#referralDetailsContent .summary-value');
            modalStatusElements.forEach(element => {
                if (element.textContent.toLowerCase().includes('status') ||
                    element.closest('.summary-item')?.querySelector('.summary-label')?.textContent?.toLowerCase().includes('status')) {
                    element.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    if (newStatus.toLowerCase() === 'cancelled') {
                        element.style.color = '#dc3545';
                        element.style.fontWeight = '600';
                    }
                }
            });
        }

        // Helper function to show success snackbar (upper right corner)
        function showSuccessMessage(message) {
            const snackbar = document.createElement('div');
            snackbar.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
                z-index: 15000;
                font-weight: 500;
                min-width: 300px;
                max-width: 500px;
                animation: slideInRight 0.3s ease;
            `;

            snackbar.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 1.2em;"></i>
                    <span style="flex: 1;">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="background: none; border: none; color: white; font-size: 1.2em; cursor: pointer; opacity: 0.8;">
                        &times;
                    </button>
                </div>
            `;

            document.body.appendChild(snackbar);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (snackbar.parentElement) {
                    snackbar.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => snackbar.remove(), 300);
                }
            }, 5000);
        }

        // Helper function to show error snackbar (upper right corner)
        function showErrorMessage(message) {
            const snackbar = document.createElement('div');
            snackbar.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
                z-index: 15000;
                font-weight: 500;
                min-width: 300px;
                max-width: 500px;
                animation: slideInRight 0.3s ease;
            `;

            snackbar.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.2em;"></i>
                    <span style="flex: 1;">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="background: none; border: none; color: white; font-size: 1.2em; cursor: pointer; opacity: 0.8;">
                        &times;
                    </button>
                </div>
            `;

            document.body.appendChild(snackbar);

            // Auto-remove after 8 seconds (longer for errors)
            setTimeout(() => {
                if (snackbar.parentElement) {
                    snackbar.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => snackbar.remove(), 300);
                }
            }, 8000);
        }

        // Cancel referral form handler
        document.getElementById('cancelReferralForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const reason = document.getElementById('cancel_reason').value.trim();
            const password = document.getElementById('cancel_employee_password').value;

            // Validate fields
            if (!reason) {
                alert('Please provide a reason for cancellation.');
                document.getElementById('cancel_reason').focus();
                return;
            }

            if (reason.length < 10) {
                alert('Cancellation reason must be at least 10 characters long.');
                document.getElementById('cancel_reason').focus();
                return;
            }

            if (!password) {
                alert('Please enter your password to confirm cancellation.');
                document.getElementById('cancel_employee_password').focus();
                return;
            }

            if (!currentReferralId) {
                alert('Unable to cancel referral: Referral ID not found.');
                return;
            }

            // Disable submit button and show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

            // Send AJAX request to cancel referral
            fetch('cancel_referral.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `referral_id=${encodeURIComponent(currentReferralId)}&reason=${encodeURIComponent(reason)}&password=${encodeURIComponent(password)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        closeModal('cancelReferralModal');

                        // Update UI to reflect cancelled status
                        updateReferralStatusInUI('cancelled', reason);

                        // Show success snackbar
                        showSuccessMessage('Referral has been successfully cancelled.');

                        // Reload page after 2 seconds to refresh the table
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);

                    } else {
                        // Show specific error message from server
                        showErrorMessage(data.message || 'Failed to cancel referral. Please try again.');
                    }
                })
                .catch(error => {
                    // Show simple error message
                    showErrorMessage('Unable to cancel referral. Please check your connection and try again.');
                })
                .finally(() => {
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
        });

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
                            window.location.href = `update_referrals.php?id=${currentReferralId}`;
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
            // Handle old-style modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Handle new-style referral modal
            const referralModal = document.getElementById('viewReferralModal');
            if (event.target === referralModal) {
                closeReferralModal();
            }
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

        // ESC key support for closing modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const referralModal = document.getElementById('viewReferralModal');
                if (referralModal && referralModal.classList.contains('show')) {
                    closeReferralModal();
                }
            }
        });

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

        // Pagination function
        function changePageSize(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', '1'); // Reset to first page when changing page size
            window.location.href = url.toString();
        }
    </script>
</body>

</html>