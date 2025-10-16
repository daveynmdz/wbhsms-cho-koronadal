<?php
// appointments_management.php - Admin Side
ob_start(); // Start output buffering to prevent any accidental output
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check if role is authorized
$authorized_roles = ['admin', 'dho', 'bhw', 'doctor', 'nurse'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: ../dashboard.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';
// Use relative path for assets - more reliable than absolute URLs
$assets_path = '../../../../assets';

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
    $appointment_id = $_POST['appointment_id'] ?? '';

    if ($action === 'cancel_appointment' && !empty($appointment_id) && is_numeric($appointment_id)) {
        $cancel_reason = $_POST['cancel_reason'] ?? '';
        $employee_password = $_POST['employee_password'] ?? '';

        if (empty($cancel_reason) || empty($employee_password)) {
            $error = "Cancel reason and password are required.";
        } else {
            try {
                // Verify employee password
                $stmt = $conn->prepare("SELECT password FROM employees WHERE employee_id = ?");
                $stmt->bind_param("i", $employee_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $employee = $result->fetch_assoc();

                if (!$employee || !password_verify($employee_password, $employee['password'])) {
                    $error = "Invalid password. Please try again.";
                } else {
                    // Update appointment status to cancelled
                    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', cancellation_reason = ? WHERE appointment_id = ?");
                    $stmt->bind_param("si", $cancel_reason, $appointment_id);
                    if ($stmt->execute()) {
                        // Log the cancellation
                        $log_stmt = $conn->prepare("INSERT INTO appointment_logs (appointment_id, patient_id, action, old_status, new_status, reason, created_by_type, created_by_id) VALUES (?, (SELECT patient_id FROM appointments WHERE appointment_id = ?), 'cancelled', 'confirmed', 'cancelled', ?, 'employee', ?)");
                        $log_stmt->bind_param("iisi", $appointment_id, $appointment_id, $cancel_reason, $employee_id);
                        $log_stmt->execute();

                        $message = "Appointment cancelled successfully.";
                    } else {
                        $error = "Failed to cancel appointment. Please try again.";
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = "An error occurred: " . $e->getMessage();
            }
        }
    }
}

// Get filter parameters  
$facility_filter = $_GET['facility_id'] ?? '';
$date_filter = $_GET['appointment_date'] ?? '';

// Pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = in_array(intval($_GET['per_page'] ?? 25), [10, 25, 50, 100]) ? intval($_GET['per_page'] ?? 25) : 25;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($facility_filter)) {
    $where_conditions[] = "a.facility_id = ?";
    $params[] = $facility_filter;
    $param_types .= 'i';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(a.scheduled_date) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        $where_clause
    ";

    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params) && !empty($param_types)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = ceil($total_records / $per_page);

    $sql = "
     SELECT a.appointment_id, a.scheduled_date, a.scheduled_time, a.status, a.service_id,
         a.cancellation_reason, a.created_at, a.updated_at,
         p.first_name, p.last_name, p.middle_name, p.username as patient_id,
         p.contact_number, p.date_of_birth, p.sex,
         f.name as facility_name, f.district as facility_district,
         b.barangay_name,
         s.name as service_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN services s ON a.service_id = s.service_id
        $where_clause
        ORDER BY a.scheduled_date ASC, a.scheduled_time ASC
        LIMIT ? OFFSET ?
    ";

    // Add pagination parameters
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';

    $stmt = $conn->prepare($sql);
    if (!empty($params) && !empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch appointments: " . $e->getMessage();
    $appointments = [];
    $total_records = 0;
    $total_pages = 0;
}

// Get statistics
$stats = [
    'total' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'checked_in' => 0
];

try {
    $stats_where_conditions = [];
    $stats_params = [];
    $stats_types = '';

    if (!empty($facility_filter)) {
        $stats_where_conditions[] = "a.facility_id = ?";
        $stats_params[] = $facility_filter;
        $stats_types .= 'i';
    }

    if (!empty($date_filter)) {
        $stats_where_conditions[] = "DATE(a.scheduled_date) = ?";
        $stats_params[] = $date_filter;
        $stats_types .= 's';
    }

    $stats_where = !empty($stats_where_conditions) ? 'WHERE ' . implode(' AND ', $stats_where_conditions) : '';

    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM appointments a $stats_where GROUP BY status");
    if (!empty($stats_params) && !empty($stats_types)) {
        $stmt->bind_param($stats_types, ...$stats_params);
    }
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
    $error = "Failed to fetch statistics: " . $e->getMessage();
}

// Fetch facilities for dropdown
$facilities = [];
try {
    $stmt = $conn->prepare("SELECT facility_id, name, district FROM facilities WHERE status = 'active' ORDER BY name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $facilities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch facilities: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Appointments Management</title>
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

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 0 15px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0, 119, 182, 0.2);
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

        .stat-card.confirmed {
            border-left: 4px solid #43e97b;
        }

        .stat-card.pending {
            border-left: 4px solid #f093fb;
        }

        .stat-card.completed {
            border-left: 4px solid #4facfe;
        }

        .stat-card.cancelled {
            border-left: 4px solid #fa709a;
        }

        .stat-card.no_show {
            border-left: 4px solid #ffba08;
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
            max-height: 70vh;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .table th {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr:hover {
            background-color: rgba(240, 247, 255, 0.6);
        }

        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .table-wrapper {
                font-size: 14px;
            }

            .table th,
            .table td {
                padding: 8px 10px;
            }

            .table th {
                font-size: 12px;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.4rem;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            .content-wrapper {
                padding: 10px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 15px 10px;
            }
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-primary {
            background-color: #0077b6;
            color: white;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-info {
            background-color: #17a2b8;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }

        .actions-group {
            display: flex;
            gap: 5px;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1050;
        }

        .alert-success {
            border-left-color: #28a745;
            color: #155724;
        }

        .alert-error {
            border-left-color: #dc3545;
            color: #721c24;
        }

        /* Dynamic alerts that appear on top of modals */
        .alert.alert-dynamic {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1100;
            min-width: 300px;
            max-width: 600px;
            margin: 0;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            animation: slideInFromTop 0.3s ease-out;
        }

        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translate(-50%, -20px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, 0);
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 0;
            border: none;
            width: 90%;
            max-width: 600px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-left: 4px solid #0077b6;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: linear-gradient(135deg, #0077b6 0%, #005577 100%);
            color: white;
            margin: 0;
            border-bottom: none;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 25px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        @media (max-width: 600px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }

        .modal-body {
            padding: 25px;
            line-height: 1.6;
            color: #444;
        }

        .appointment-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .details-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid #0077b6;
        }

        .details-section h4 {
            margin: 0 0 15px 0;
            color: #0077b6;
            font-size: 16px;
            font-weight: 600;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: 500;
            color: #6c757d;
            margin-right: 10px;
        }

        .detail-value {
            color: #333;
            text-align: right;
        }

        .detail-value.highlight {
            background: #e3f2fd;
            color: #0277bd;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .close {
            color: rgba(255, 255, 255, 0.8);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close:hover,
        .close:focus {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(90deg);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #0077b6;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: #0077b6;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .breadcrumb a {
            color: #0077b6;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            color: #023e8a;
        }

        .breadcrumb i {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-top: 1px solid #dee2e6;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
            color: #6c757d;
        }

        .records-info {
            font-weight: 500;
            color: #333;
        }

        .records-info strong {
            color: #0077b6;
        }

        .page-size-selector {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-size-selector label {
            font-weight: 500;
            color: #6c757d;
        }

        .page-size-selector select {
            padding: 5px 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: white;
        }

        .page-size-selector select:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 2px rgba(0, 119, 182, 0.2);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 5px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            color: #0077b6;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            background: #f8f9fa;
            border-color: #0077b6;
            transform: translateY(-1px);
        }

        .pagination-btn.active {
            background: #0077b6;
            color: white;
            border-color: #0077b6;
        }

        .pagination-btn.disabled {
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .pagination-btn.prev,
        .pagination-btn.next {
            padding: 8px 15px;
        }

        .pagination-ellipsis {
            padding: 8px 4px;
            color: #6c757d;
        }

        /* Mobile responsive pagination */
        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .pagination-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .pagination-controls {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .pagination-btn {
                padding: 6px 8px;
                font-size: 12px;
                min-width: 32px;
            }

            .pagination-btn.prev,
            .pagination-btn.next {
                padding: 6px 10px;
            }
        }

        /* Time slot styling */
        .time-slot {
            background: #e3f2fd;
            color: #0277bd;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Loader animation */
        .loader {
            border: 4px solid rgba(0, 119, 182, 0.2);
            border-radius: 50%;
            border-top: 4px solid #0077b6;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
            display: block;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'appointments';
    include $root_path . '/includes/sidebar_admin.php';
    ?>

    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Appointments Management</span>
        </div>

        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> Appointments Management</h1>
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
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card confirmed">
                <div class="stat-number"><?php echo $stats['confirmed']; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card cancelled">
                <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card no_show">
                <div class="stat-number"><?php echo $stats['checked_in']; ?></div>
                <div class="stat-label">Checked In</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <div class="section-header" style="margin-bottom: 15px;">
                <h4 style="margin: 0;color: var(--primary-dark);font-size: 18px;font-weight: 600;">
                    <i class="fas fa-filter"></i> Search &amp; Filter Options
                </h4>
            </div>
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label for="facility_id">Facility</label>
                    <select name="facility_id" id="facility_id" onchange="this.form.submit();">
                        <option value="">All Facilities</option>
                        <?php foreach ($facilities as $facility): ?>
                            <option value="<?php echo $facility['facility_id']; ?>"
                                <?php echo $facility_filter == $facility['facility_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($facility['name']); ?> - <?php echo htmlspecialchars($facility['district']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="appointment_date">Appointment Date</label>
                    <input type="date" name="appointment_date" id="appointment_date"
                        value="<?php echo htmlspecialchars($date_filter); ?>"
                        max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>"
                        onchange="this.form.submit();">
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Appointments Table -->
<div class="card-container">
    <div class="section-header">
        <h4 style="margin: 0;color: var(--primary-dark);font-size: 18px;font-weight: 600;">
                    <i class="fas fa-calendar-check"></i> Appointments
                </h4>

    </div>
        <div class="table-container">
            <div class="table-wrapper">
                <?php if (empty($appointments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No appointments found</h3>
                        <p>No appointments match your current filters.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Patient ID</th>
                                <th>Date</th>
                                <th>Time Slot</th>
                                <th>Status</th>
                                <th>Facility</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="<?php echo $assets_path . '/images/user-default.png'; ?>"
                                                alt="Profile" class="profile-img">
                                            <div>
                                                <strong><?php echo htmlspecialchars($appointment['last_name'] . ', ' . $appointment['first_name']); ?></strong>
                                                <?php if (!empty($appointment['middle_name'])): ?>
                                                    <br><small><?php echo htmlspecialchars($appointment['middle_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['patient_id']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($appointment['scheduled_date'])); ?></td>
                                    <td>
                                        <span class="time-slot"><?php echo date('g:i A', strtotime($appointment['scheduled_time'])); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = '';
                                        switch ($appointment['status']) {
                                            case 'confirmed':
                                                $badge_class = 'badge-success';
                                                break;
                                            case 'completed':
                                                $badge_class = 'badge-primary';
                                                break;
                                            case 'cancelled':
                                                $badge_class = 'badge-danger';
                                                break;
                                            case 'checked_in':
                                                $badge_class = 'badge-warning';
                                                break;
                                            default:
                                                $badge_class = 'badge-info';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['facility_name']); ?></td>
                                    <td>
                                        <div class="actions-group">
                                            <button onclick="viewAppointment(<?php echo $appointment['appointment_id']; ?>)"
                                                class="btn btn-sm btn-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (in_array($appointment['status'], ['confirmed', 'checked_in'])): ?>
                                                <button onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)"
                                                    class="btn btn-sm btn-danger" title="Cancel Appointment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        <div class="records-info">
                            Showing <strong><?php echo $offset + 1; ?></strong> to
                            <strong><?php echo min($offset + $per_page, $total_records); ?></strong>
                            of <strong><?php echo $total_records; ?></strong> appointments
                        </div>
                        <div class="page-size-selector">
                            <label>Show:</label>
                            <select onchange="changePageSize(this.value)">
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn prev">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if ($start > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn">1</a>
                            <?php if ($start > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn next">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
</div>
    </section>

    <!-- View Appointment Modal -->
    <div id="viewAppointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-check"></i> Appointment Details</h3>
                <button type="button" class="close" onclick="closeModal('viewAppointmentModal')">&times;</button>
            </div>
            <div class="modal-body" id="appointmentDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewAppointmentModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div id="cancelAppointmentModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Cancel Appointment</h3>
                <button type="button" class="close" onclick="closeModal('cancelAppointmentModal')">&times;</button>
            </div>
            <form id="cancelAppointmentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="cancel_appointment">
                    <input type="hidden" name="appointment_id" id="cancelAppointmentId">

                    <div class="form-group">
                        <label for="cancel_reason">Reason for Cancellation *</label>
                        <textarea name="cancel_reason" id="cancel_reason" class="form-control" rows="4"
                            placeholder="Please provide a reason for cancelling this appointment..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="employee_password">Confirm with Your Password *</label>
                        <input type="password" name="employee_password" id="employee_password" class="form-control"
                            placeholder="Enter your password to confirm" required>
                    </div>

                    <p style="color: #dc3545; font-size: 14px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone. The patient will be notified of the cancellation.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cancelAppointmentModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentAppointmentId = null;

        function viewAppointment(appointmentId) {
            currentAppointmentId = appointmentId;

            // Show loading
            document.getElementById('appointmentDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div class="loader"></div>
                    <p style="margin-top: 10px; color: #6c757d;">Loading appointment details...</p>
                </div>
            `;
            document.getElementById('viewAppointmentModal').style.display = 'block';

            // Get appointment data
            fetch(`../../../../api/get_appointment_details.php?appointment_id=${appointmentId}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayAppointmentDetails(data.appointment);
                    } else {
                        showErrorInModal('Error loading appointment details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorInModal('Network error: Unable to load appointment details. Please check your connection and try again.');
                });
        }

        function showErrorInModal(message) {
            document.getElementById('appointmentDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 30px; color: #dc3545;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.7;"></i>
                    <h4 style="color: #dc3545; margin-bottom: 10px;">Error</h4>
                    <p style="margin: 0; line-height: 1.5;">${message}</p>
                    <button onclick="closeModal('viewAppointmentModal')" class="btn btn-secondary" style="margin-top: 20px;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        }

        function displayAppointmentDetails(appointment) {
            const content = `
                <div class="appointment-details-grid">
                    <div class="details-section">
                        <h4><i class="fas fa-user"></i> Patient Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value">${appointment.patient_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Patient ID:</span>
                            <span class="detail-value">${appointment.patient_id || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">${appointment.contact_number || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Age/Sex:</span>
                            <span class="detail-value">${(appointment.age || 'N/A')}/${(appointment.sex || 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Barangay:</span>
                            <span class="detail-value">${appointment.barangay_name || 'N/A'}</span>
                        </div>
                    </div>
                    
                    <div class="details-section">
                        <h4><i class="fas fa-calendar"></i> Appointment Details</h4>
                        <div class="detail-item">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value">${appointment.appointment_date || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Time Slot:</span>
                            <span class="detail-value highlight">${appointment.time_slot || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">${appointment.status || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Facility:</span>
                            <span class="detail-value">${appointment.facility_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Service:</span>
                            <span class="detail-value">${appointment.service_name || 'General Consultation'}</span>
                        </div>
                    </div>
                </div>
                
                ${appointment.cancel_reason ? `
                    <div class="details-section" style="border-left: 4px solid #dc3545; background: #fff5f5; margin-top: 20px;">
                        <h4><i class="fas fa-times-circle" style="color: #dc3545;"></i> Cancellation Details</h4>
                        <div class="detail-item">
                            <span class="detail-label">Reason:</span>
                            <span class="detail-value">${appointment.cancel_reason}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Cancelled At:</span>
                            <span class="detail-value">${appointment.cancelled_at || 'N/A'}</span>
                        </div>
                    </div>
                ` : ''}
            `;

            document.getElementById('appointmentDetailsContent').innerHTML = content;
        }

        function cancelAppointment(appointmentId) {
            currentAppointmentId = appointmentId;
            document.getElementById('cancelAppointmentId').value = appointmentId;
            document.getElementById('cancel_reason').value = '';
            document.getElementById('employee_password').value = '';
            document.getElementById('cancelAppointmentModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'cancelAppointmentModal') {
                document.getElementById('cancelAppointmentForm').reset();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['viewAppointmentModal', 'cancelAppointmentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }

        // ESC key support for closing modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModals = ['viewAppointmentModal', 'cancelAppointmentModal'];
                openModals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (modal.style.display === 'block') {
                        closeModal(modalId);
                    }
                });
            }
        });

        // Pagination function
        function changePageSize(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }

        // Auto-dismiss alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.parentElement) {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-10px)';
                        setTimeout(() => alert.remove(), 300);
                    }
                }, 5000);
            });

            // Add form validation
            const cancelForm = document.getElementById('cancelAppointmentForm');
            if (cancelForm) {
                cancelForm.addEventListener('submit', function(e) {
                    const reason = document.getElementById('cancel_reason').value.trim();
                    const password = document.getElementById('employee_password').value.trim();

                    if (!reason) {
                        e.preventDefault();
                        showErrorMessage('Please provide a reason for cancellation.');
                        return;
                    }

                    if (reason.length < 10) {
                        e.preventDefault();
                        showErrorMessage('Cancellation reason must be at least 10 characters long.');
                        return;
                    }

                    if (!password) {
                        e.preventDefault();
                        showErrorMessage('Please enter your password to confirm cancellation.');
                        return;
                    }
                });
            }
        });

        // Helper function to show error message
        function showErrorMessage(message) {
            const existingAlert = document.querySelector('.alert-dynamic');
            if (existingAlert) existingAlert.remove();

            const alert = document.createElement('div');
            alert.className = 'alert alert-error alert-dynamic';
            alert.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i> 
                ${message}
                <button type="button" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7; color: inherit; padding: 0; margin-left: auto;" onclick="this.parentElement.remove();">&times;</button>
            `;

            document.body.appendChild(alert);

            setTimeout(() => {
                if (alert.parentElement) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translate(-50%, -20px)';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 8000);
        }
    </script>
</body>

</html>
<?php ob_end_flush(); // End output buffering and send output 
?>