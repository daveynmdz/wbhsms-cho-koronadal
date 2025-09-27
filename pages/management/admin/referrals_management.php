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
            align-content: flex-start;
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
            }
            
            .modal-btn {
                padding: 0.5rem 0.8rem;
                font-size: 0.8em;
                min-width: 70px;
                max-width: 100px;
                flex: 1;
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
            }
            
            .modal-btn {
                max-width: 100%;
                padding: 0.75rem 1rem;
                font-size: 0.85em;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
            <div class="stat-card canceled">
                <div class="stat-number"><?php echo number_format($stats['canceled'] ?? 0); ?></div>
                <div class="stat-label">Canceled</div>
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
                        
                        // Determine destination based on destination_type
                        if ($referral['destination_type'] === 'external') {
                            $destination = $referral['external_facility_name'] ?: 'External Facility';
                        } else {
                            $destination = $referral['referred_facility_name'] ?: 'Internal Facility';
                        }

                        // Determine badge class based on status
                        $badge_class = 'badge-secondary';
                        switch ($referral['status']) {
                            case 'active': $badge_class = 'badge-success'; break;
                            case 'accepted': $badge_class = 'badge-info'; break;
                            case 'completed': $badge_class = 'badge-primary'; break;
                            case 'cancelled': $badge_class = 'badge-danger'; break;
                            default: $badge_class = 'badge-secondary'; break;
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
                <button type="button" class="modal-btn modal-btn-warning" onclick="editReferral()" id="editReferralBtn">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button type="button" class="modal-btn modal-btn-danger" onclick="cancelReferral()" id="cancelReferralBtn">
                    <i class="fas fa-ban"></i> Void
                </button>
                <button type="button" class="modal-btn modal-btn-success" onclick="markComplete()" id="markCompleteBtn">
                    <i class="fas fa-check"></i> Complete
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="printReferral()">
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
    </script>
</body>

</html>