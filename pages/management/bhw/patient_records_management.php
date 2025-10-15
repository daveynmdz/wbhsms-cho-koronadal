<?php
// Include employee session configuration
// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Use relative path for assets - more reliable than absolute URLs
$assets_path = '../../../assets';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../auth/employee_login.php");
    exit();
}

// Set active page for sidebar highlighting
$activePage = 'patients';

// Define role-based permissions - BHW specific
$canEdit = false; // BHW cannot edit patient records
$canView = ($_SESSION['role'] === 'bhw');

if (!$canView) {
    $role = $_SESSION['role'];
    header("Location: ../../$role/dashboard.php");
    exit();
}

// Get BHW's barangay_id from their facility assignment
$bhw_barangay_sql = "SELECT f.barangay_id 
                     FROM employees e 
                     JOIN facilities f ON e.facility_id = f.facility_id 
                     WHERE e.employee_id = ? AND e.role_id = 6";
$bhw_barangay_stmt = $conn->prepare($bhw_barangay_sql);
$bhw_barangay_stmt->bind_param("i", $_SESSION['employee_id']);
$bhw_barangay_stmt->execute();
$bhw_barangay_result = $bhw_barangay_stmt->get_result();

if ($bhw_barangay_result->num_rows === 0) {
    // BHW not assigned to a facility or invalid setup
    echo "<script>alert('Access denied: No facility assignment found.'); window.location.href='dashboard.php';</script>";
    exit();
}

$bhw_barangay = $bhw_barangay_result->fetch_assoc()['barangay_id'];

// Handle AJAX requests for search and filter
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$patientIdFilter = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';
$firstNameFilter = isset($_GET['first_name']) ? $_GET['first_name'] : '';
$lastNameFilter = isset($_GET['last_name']) ? $_GET['last_name'] : '';
$middleNameFilter = isset($_GET['middle_name']) ? $_GET['middle_name'] : '';
$birthdayFilter = isset($_GET['birthday']) ? $_GET['birthday'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Count total records for pagination - RESTRICTED TO BHW'S BARANGAY
$countSql = "SELECT COUNT(DISTINCT p.patient_id) as total 
             FROM patients p 
             LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
             WHERE p.barangay_id = ?";

$params = [$bhw_barangay];
$types = "i";

if (!empty($searchQuery)) {
    $countSql .= " AND (p.username LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? 
                  OR b.barangay_name LIKE ? OR p.date_of_birth LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    $types .= "sssss";
}

if (!empty($patientIdFilter)) {
    $countSql .= " AND p.username LIKE ?";
    $patientIdParam = "%{$patientIdFilter}%";
    array_push($params, $patientIdParam);
    $types .= "s";
}

if (!empty($firstNameFilter)) {
    $countSql .= " AND p.first_name LIKE ?";
    $firstNameParam = "%{$firstNameFilter}%";
    array_push($params, $firstNameParam);
    $types .= "s";
}

if (!empty($lastNameFilter)) {
    $countSql .= " AND p.last_name LIKE ?";
    $lastNameParam = "%{$lastNameFilter}%";
    array_push($params, $lastNameParam);
    $types .= "s";
}

if (!empty($middleNameFilter)) {
    $countSql .= " AND p.middle_name LIKE ?";
    $middleNameParam = "%{$middleNameFilter}%";
    array_push($params, $middleNameParam);
    $types .= "s";
}

if (!empty($birthdayFilter)) {
    $countSql .= " AND p.date_of_birth LIKE ?";
    $birthdayParam = "%{$birthdayFilter}%";
    array_push($params, $birthdayParam);
    $types .= "s";
}

if (!empty($statusFilter)) {
    $countSql .= " AND p.status = ?";
    array_push($params, $statusFilter);
    $types .= "s";
}

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Count active patients - RESTRICTED TO BHW'S BARANGAY
$activeCountSql = "SELECT COUNT(DISTINCT p.patient_id) as active_count 
                   FROM patients p 
                   LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
                   WHERE p.status = 'active' AND p.barangay_id = ?";
$activeParams = [$bhw_barangay];
$activeTypes = "i";

if (!empty($searchQuery)) {
    $activeCountSql .= " AND (p.username LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? 
                      OR b.barangay_name LIKE ? OR p.date_of_birth LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    array_push($activeParams, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    $activeTypes .= "sssss";
}

if (!empty($patientIdFilter)) {
    $activeCountSql .= " AND p.username LIKE ?";
    $patientIdParam = "%{$patientIdFilter}%";
    array_push($activeParams, $patientIdParam);
    $activeTypes .= "s";
}

if (!empty($firstNameFilter)) {
    $activeCountSql .= " AND p.first_name LIKE ?";
    $firstNameParam = "%{$firstNameFilter}%";
    array_push($activeParams, $firstNameParam);
    $activeTypes .= "s";
}

if (!empty($lastNameFilter)) {
    $activeCountSql .= " AND p.last_name LIKE ?";
    $lastNameParam = "%{$lastNameFilter}%";
    array_push($activeParams, $lastNameParam);
    $activeTypes .= "s";
}

if (!empty($middleNameFilter)) {
    $activeCountSql .= " AND p.middle_name LIKE ?";
    $middleNameParam = "%{$middleNameFilter}%";
    array_push($activeParams, $middleNameParam);
    $activeTypes .= "s";
}

if (!empty($birthdayFilter)) {
    $activeCountSql .= " AND p.date_of_birth LIKE ?";
    $birthdayParam = "%{$birthdayFilter}%";
    array_push($activeParams, $birthdayParam);
    $activeTypes .= "s";
}

$activeCountStmt = $conn->prepare($activeCountSql);
$activeCountStmt->bind_param($activeTypes, ...$activeParams);
$activeCountStmt->execute();
$activeCountResult = $activeCountStmt->get_result();
$activePatients = $activeCountResult->fetch_assoc()['active_count'];

// Count inactive patients - RESTRICTED TO BHW'S BARANGAY
$inactiveCountSql = "SELECT COUNT(DISTINCT p.patient_id) as inactive_count 
                     FROM patients p 
                     LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
                     WHERE p.status = 'inactive' AND p.barangay_id = ?";
$inactiveParams = [$bhw_barangay];
$inactiveTypes = "i";

if (!empty($searchQuery)) {
    $inactiveCountSql .= " AND (p.username LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? 
                        OR b.barangay_name LIKE ? OR p.date_of_birth LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    array_push($inactiveParams, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    $inactiveTypes .= "sssss";
}

if (!empty($patientIdFilter)) {
    $inactiveCountSql .= " AND p.username LIKE ?";
    $patientIdParam = "%{$patientIdFilter}%";
    array_push($inactiveParams, $patientIdParam);
    $inactiveTypes .= "s";
}

if (!empty($firstNameFilter)) {
    $inactiveCountSql .= " AND p.first_name LIKE ?";
    $firstNameParam = "%{$firstNameFilter}%";
    array_push($inactiveParams, $firstNameParam);
    $inactiveTypes .= "s";
}

if (!empty($lastNameFilter)) {
    $inactiveCountSql .= " AND p.last_name LIKE ?";
    $lastNameParam = "%{$lastNameFilter}%";
    array_push($inactiveParams, $lastNameParam);
    $inactiveTypes .= "s";
}

if (!empty($middleNameFilter)) {
    $inactiveCountSql .= " AND p.middle_name LIKE ?";
    $middleNameParam = "%{$middleNameFilter}%";
    array_push($inactiveParams, $middleNameParam);
    $inactiveTypes .= "s";
}

if (!empty($birthdayFilter)) {
    $inactiveCountSql .= " AND p.date_of_birth LIKE ?";
    $birthdayParam = "%{$birthdayFilter}%";
    array_push($inactiveParams, $birthdayParam);
    $inactiveTypes .= "s";
}

$inactiveCountStmt = $conn->prepare($inactiveCountSql);
$inactiveCountStmt->bind_param($inactiveTypes, ...$inactiveParams);
$inactiveCountStmt->execute();
$inactiveCountResult = $inactiveCountStmt->get_result();
$inactivePatients = $inactiveCountResult->fetch_assoc()['inactive_count'];

// Get patient records - RESTRICTED TO BHW'S BARANGAY
$sql = "SELECT p.patient_id, p.username, p.status, 
        p.first_name, p.last_name, p.middle_name, p.date_of_birth, p.sex, p.contact_number, 
        pi.profile_photo,
        b.barangay_name,
        CONCAT(ec.emergency_last_name, ', ', ec.emergency_first_name, ' ', LEFT(ec.emergency_middle_name, 1), '.') as contact_name, 
        ec.emergency_contact_number as emergency_contact
        FROM patients p
        LEFT JOIN personal_information pi ON p.patient_id = pi.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN emergency_contact ec ON p.patient_id = ec.patient_id
        WHERE p.barangay_id = ?";

// Apply the same filters for the main query - WITH BHW BARANGAY RESTRICTION
$params = [$bhw_barangay];
$types = "i";

if (!empty($searchQuery)) {
    $sql .= " AND (p.username LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? 
              OR b.barangay_name LIKE ? OR p.date_of_birth LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    $types .= "sssss";
}

if (!empty($patientIdFilter)) {
    $sql .= " AND p.username LIKE ?";
    $patientIdParam = "%{$patientIdFilter}%";
    array_push($params, $patientIdParam);
    $types .= "s";
}

if (!empty($firstNameFilter)) {
    $sql .= " AND p.first_name LIKE ?";
    $firstNameParam = "%{$firstNameFilter}%";
    array_push($params, $firstNameParam);
    $types .= "s";
}

if (!empty($lastNameFilter)) {
    $sql .= " AND p.last_name LIKE ?";
    $lastNameParam = "%{$lastNameFilter}%";
    array_push($params, $lastNameParam);
    $types .= "s";
}

if (!empty($middleNameFilter)) {
    $sql .= " AND p.middle_name LIKE ?";
    $middleNameParam = "%{$middleNameFilter}%";
    array_push($params, $middleNameParam);
    $types .= "s";
}

if (!empty($birthdayFilter)) {
    $sql .= " AND p.date_of_birth LIKE ?";
    $birthdayParam = "%{$birthdayFilter}%";
    array_push($params, $birthdayParam);
    $types .= "s";
}

if (!empty($statusFilter)) {
    $sql .= " AND p.status = ?";
    array_push($params, $statusFilter);
    $types .= "s";
}

$sql .= " ORDER BY p.last_name ASC LIMIT ? OFFSET ?";
array_push($params, $recordsPerPage, $offset);
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get barangay name for BHW display
$barangay_name_sql = "SELECT barangay_name FROM barangay WHERE barangay_id = ?";
$barangay_name_stmt = $conn->prepare($barangay_name_sql);
$barangay_name_stmt->bind_param("i", $bhw_barangay);
$barangay_name_stmt->execute();
$barangay_name_result = $barangay_name_stmt->get_result();
$bhw_barangay_name = $barangay_name_result->fetch_assoc()['barangay_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records Management (<?php echo htmlspecialchars($bhw_barangay_name); ?>) | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Additional styles for patient records management */
        :root {
            --primary: #0077b6;
            --primary-dark: #03045e;
            --secondary: #6c757d;
            --success: #2d6a4f;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #d00000;
            --light: #f8f9fa;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }
        .loader {
            border: 5px solid rgba(240, 240, 240, 0.5);
            border-radius: 50%;
            border-top: 5px solid var(--primary);
            width: 30px;
            height: 30px;
            animation: spin 1.5s linear infinite;
            margin: 0 auto;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
            margin-top: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: var(--shadow);
        }
        
        table th {
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
        }
        
        table th.sortable::after {
            content: '\f0dc';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: 5px;
            opacity: 0.5;
            font-size: 14px;
        }
        
        table th.sort-asc::after {
            content: '\f0de';
            opacity: 1;
        }
        
        table th.sort-desc::after {
            content: '\f0dd';
            opacity: 1;
        }
        
        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        table tr:hover {
            background-color: rgba(240, 247, 255, 0.6);
            transition: background-color 0.2s;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .action-btn {
            margin-right: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            color: white;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
        }
        
        .equal-width {
            width: calc(50% - 5px);
            max-height: fit-content;
            padding: 10px;
            text-align: center;
            font-weight: 500;
            letter-spacing: 0.3px;
            gap: 10px;
        }
        
        .button-container {
            justify-content: space-between;
            gap: 10px;
        }
        
        .button-container .dropdown {
            width: 50%;
        }
        
        .button-container .dropdown button {
            width: 100%;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .bg-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .bg-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            justify-content: center;
            margin-top: 25px;
            gap: 8px;
        }
        
        .pagination li {
            margin: 0;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid var(--primary);
            color: var(--primary);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
            font-weight: 500;
            min-width: 38px;
            text-align: center;
            display: inline-block;
        }
        
        .pagination a:hover:not(.disabled a) {
            background-color: rgba(0, 119, 182, 0.1);
            transform: translateY(-2px);
        }
        
        .pagination .active a {
            background: linear-gradient(135deg, #0096c7, #0077b6);
            color: white;
            border-color: transparent;
            box-shadow: 0 2px 5px rgba(0, 119, 182, 0.3);
        }
        
        .pagination .disabled a {
            color: #ccc;
            border-color: #eee;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        tr:hover .profile-img {
            transform: scale(1.05);
        }
        
        .header {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 12px 15px;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 15px;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header h5 {
            margin: 0;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .info p {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info p:last-child {
            border-bottom: none;
        }
        
        .info strong {
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        /* Card content styling */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            color: var(--primary-dark);
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.show {
            display: block !important;
        }
        
        .modal-dialog {
            max-width: 450px;
            margin: 50px auto;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        
        .modal.show .modal-dialog {
            transform: translateY(0);
        }
        
        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-dark);
            color: white;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: white;
            transition: color 0.2s ease;
        }
        
        .btn-close:hover {
            color: var(--light);
        }

        /* Radio option pulse animation */
        @keyframes pulseEffect {
            0% { transform: scale(1.02); }
            50% { transform: scale(1.04); }
            100% { transform: scale(1.02); }
        }
        
        .pulse-animation {
            animation: pulseEffect 0.3s ease;
        }
        
        /* Form inputs */
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.2);
            outline: none;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            font-size: 14px;
        }
        
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.2);
            outline: none;
        }
        
        .input-group {
            display: flex;
            position: relative;
        }
        
        .input-group-text {
            padding: 10px 15px;
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-right: none;
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            display: flex;
            align-items: center;
            color: #64748b;
        }
        
        .input-group .form-control {
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            margin-bottom: 0;
            flex: 1;
        }
        
        /* Utility classes */
        .d-flex {
            display: flex;
        }
        
        .me-2 {
            margin-right: 10px;
        }
        
        .mb-2 {
            margin-bottom: 10px;
        }
        
        .mt-4 {
            margin-top: 20px;
        }
        
        .justify-content-center {
            justify-content: center;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6c757d;
            font-style: italic;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        /* Header with badge */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .total-count .badge {
            font-size: 14px;
            padding: 6px 12px;
            margin-right: 8px;
        }
        
        .bg-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }
        
        /* Responsive grid */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 0 15px;
        }
        
        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding: 0 15px;
        }
        
        .col-md-3 {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 0 15px;
        }
        
        .col-md-2 {
            flex: 0 0 16.666667%;
            max-width: 16.666667%;
            padding: 0 15px;
        }
        
        @media (max-width: 768px) {
            .col-md-4, .col-md-3, .col-md-2 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 10px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }
        
        /* Dropdown */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            transition: transform 0.2s ease;
        }
        
        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 5px);
            background-color: #fff;
            min-width: 180px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-radius: var(--border-radius);
            padding: 8px 0;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        
        .dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            clear: both;
            text-decoration: none;
            color: #333;
            transition: background-color 0.15s ease;
        }
        
        .dropdown-item:hover {
            background-color: rgba(0, 119, 182, 0.1);
        }
        
        .dropdown-item i {
            color: var(--primary);
        }
        
        /* Alert styles */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left-width: 4px;
            border-left-style: solid;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
            border-left-color: #ffc107;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            border-left-color: #dc3545;
        }
        
        .alert i {
            margin-right: 5px;
        }
        
        /* Form styling for modals */
        .form-label {
            font-weight: 500;
            color: var(--primary-dark);
            margin-bottom: 5px;
            display: block;
        }
        
        .form-check {
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 5px;
            transition: background-color 0.15s ease;
        }
        
        .form-check:hover {
            background-color: rgba(0, 119, 182, 0.05);
        }
        
        .form-check-input {
            margin-top: 0.3em;
        }
        
        .form-check-label {
            padding-left: 5px;
        }
        
        .d-none {
            display: none !important;
        }
        
        /* Spinner for loading states */
        .spinner-border {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            vertical-align: middle;
            border: 0.2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
            margin-right: 5px;
        }
        
        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
        
        /* Section header styling */
        .section-header {
            padding: 0 0 15px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0, 119, 182, 0.2);
        }
        
        .section-header h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 18px;
            font-weight: 600;
        }
        
        .section-header h4 i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Breadcrumb styling */
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

        /* Page header styling */
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
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header h1 i {
            color: #48cae4;
        }

        /* Total count badges styling */
        .total-count {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .total-count .badge {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: default;
        }

        .total-count .badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Mobile responsive styling */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .total-count {
                align-self: stretch;
                justify-content: space-between;
            }

            .total-count .badge {
                flex: 1;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .total-count {
                flex-direction: column;
                gap: 0.5rem;
            }

            .total-count .badge {
                flex: none;
                width: 100%;
            }
        }

        /* BHW specific styling */
        .barangay-restriction-notice {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 4px solid var(--primary);
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            color: var(--primary-dark);
            font-size: 14px;
        }
        
        .barangay-restriction-notice i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Card container styling */
        .card-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .card-container .section-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-container .section-header h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .card-container .section-header h4 i {
            margin-right: 8px;
            color: var(--primary);
        }

        /* Breadcrumb styling */
        .breadcrumb {
            margin-bottom: 20px;
            padding: 0;
            background: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb i.fas.fa-chevron-right {
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Include sidebar -->
    <?php include '../../../includes/sidebar_bhw.php'; ?>
    
    <div class="homepage">
        <div class="main-content">
            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb" style="margin-top: 50px;">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Patient Records Management</span>
            </div>

            <div class="page-header">
                <h1><i class="fas fa-users"></i> Patient Records Management</h1>
                <div class="total-count">
                    <span class="badge bg-success"><?php echo $totalRecords; ?> Total Patients</span>
                    <span class="badge bg-primary"><?php echo $activePatients; ?> Active</span>
                    <span class="badge bg-danger"><?php echo $inactivePatients; ?> Inactive</span>
                </div>
            </div>

            <!-- Barangay Restriction Notice -->
            <div class="barangay-restriction-notice">
                <i class="fas fa-map-marker-alt"></i>
                <strong>Barangay Access:</strong> You are viewing patients from <strong><?php echo htmlspecialchars($bhw_barangay_name); ?></strong> only.
            </div>
            
            <!-- Search and Filter Section -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-filter"></i> Search & Filter Options</h4>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="General search..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" id="patientIdInput" class="form-control" placeholder="Patient ID" value="<?php echo htmlspecialchars($patientIdFilter); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" id="firstNameInput" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($firstNameFilter); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" id="lastNameInput" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($lastNameFilter); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" id="middleNameInput" class="form-control" placeholder="Middle Name" value="<?php echo htmlspecialchars($middleNameFilter); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            <input type="date" id="birthdayInput" class="form-control" placeholder="Birthday" value="<?php echo htmlspecialchars($birthdayFilter); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($statusFilter == 'active' ? 'selected' : ''); ?>>Active</option>
                            <option value="inactive" <?php echo ($statusFilter == 'inactive' ? 'selected' : ''); ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex button-container">
                        <button id="clearFilters" class="action-btn btn-secondary equal-width">
                            <i class="fas fa-times-circle"></i> Clear Filters
                        </button>
                        <div class="dropdown">
                            <button class="action-btn btn-success dropdown-toggle equal-width" type="button" id="exportDropdown">
                                <i class="fas fa-file-export"></i> Export Data
                            </button>
                            <ul class="dropdown-menu" id="exportMenu">
                                <li><a class="dropdown-item" href="#" id="exportCSV"><i class="fas fa-file-csv"></i> Export to CSV</a></li>
                                <li><a class="dropdown-item" href="#" id="exportXLSX"><i class="fas fa-file-excel"></i> Export to Excel</a></li>
                                <li><a class="dropdown-item" href="#" id="exportPDF"><i class="fas fa-file-pdf"></i> Export to PDF</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Loader -->
            <div class="text-center" style="padding: 15px 0;">
                <div id="loader" class="loader"></div>
            </div>
            
            <!-- Patient Records Table -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-table"></i> Patient Records</h4>
                </div>
                <div class="table-responsive">
                    <table id="patientTable">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;"> </th>
                                        <th class="sortable" data-column="username">Patient ID</th>
                                        <th class="sortable" data-column="full_name">Full Name</th>
                                        <th class="sortable" data-column="dob">DOB</th>
                                        <th class="sortable" data-column="sex">Sex</th>
                                        <th class="sortable" data-column="barangay">Barangay</th>
                                        <th class="sortable" data-column="contact">Contact</th>
                                        <th class="sortable" data-column="status">Status</th>
                                        <th style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($patient = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($patient['profile_photo'])): ?>
                                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($patient['profile_photo']); ?>" 
                                                             class="profile-img" alt="Patient Photo">
                                                    <?php else: ?>
                                                        <img src="<?php echo $assets_path; ?>/images/user-default.png" 
                                                             class="profile-img" alt="Patient Photo">
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($patient['username']); ?></strong></td>
                                                <td>
                                                    <?php 
                                                    $fullName = $patient['last_name'] . ', ' . $patient['first_name'];
                                                    if (!empty($patient['middle_name'])) {
                                                        $fullName .= ' ' . substr($patient['middle_name'], 0, 1) . '.';
                                                    }
                                                    echo htmlspecialchars($fullName); 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($patient['date_of_birth'])) {
                                                        $dob = new DateTime($patient['date_of_birth']);
                                                        echo $dob->format('M d, Y');
                                                    } else {
                                                        echo '<span class="text-muted">N/A</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo !empty($patient['sex']) ? htmlspecialchars($patient['sex']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                <td><?php echo !empty($patient['barangay_name']) ? htmlspecialchars($patient['barangay_name']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                <td><?php echo !empty($patient['contact_number']) ? htmlspecialchars($patient['contact_number']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                <td>
                                                    <span class="badge <?php echo ($patient['status'] == 'active') ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($patient['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="../../../patient/profile/profile.php?patient_id=<?php echo $patient['patient_id']; ?>&view_mode=bhw" 
                                                        class="action-btn btn-info" title="View Profile (BHW Mode)">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="action-btn btn-primary view-contact" 
                                                                data-id="<?php echo $patient['patient_id']; ?>"
                                                                data-username="<?php echo htmlspecialchars($patient['username']); ?>"
                                                                data-name="<?php echo htmlspecialchars($fullName); ?>"
                                                                data-dob="<?php echo !empty($patient['date_of_birth']) ? $dob->format('M d, Y') : 'N/A'; ?>"
                                                                data-sex="<?php echo !empty($patient['sex']) ? htmlspecialchars($patient['sex']) : 'N/A'; ?>"
                                                                data-contact="<?php echo !empty($patient['contact_number']) ? htmlspecialchars($patient['contact_number']) : 'N/A'; ?>"
                                                                data-barangay="<?php echo !empty($patient['barangay_name']) ? htmlspecialchars($patient['barangay_name']) : 'N/A'; ?>"
                                                                data-emergency-name="<?php echo !empty($patient['contact_name']) ? htmlspecialchars($patient['contact_name']) : 'N/A'; ?>"
                                                                data-emergency-contact="<?php echo !empty($patient['emergency_contact']) ? htmlspecialchars($patient['emergency_contact']) : 'N/A'; ?>"
                                                                data-photo="<?php echo !empty($patient['profile_photo']) ? 'data:image/jpeg;base64,'.base64_encode($patient['profile_photo']) : $assets_path . '/images/user-default.png'; ?>"
                                                                title="View Contact">
                                                            <i class="fas fa-address-card"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <div style="padding: 30px 0;">
                                                    <i class="fas fa-search" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                                    <p>No patient records found in <?php echo htmlspecialchars($bhw_barangay_name); ?>.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="mt-4">
                            <ul class="pagination">
                                <li class="<?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a href="#" data-page="<?php echo $page-1; ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <li class="disabled">
                                            <span>...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <li class="<?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a href="#" data-page="<?php echo $page+1; ?>">Next</a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contact Modal -->
    <div class="modal fade" id="contactModal" tabindex="-1" role="dialog" aria-labelledby="contactModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel">Patient ID Card</h5>
                    <button type="button" class="btn-close" id="closeContactModal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="card-container" style="box-shadow: none; padding: 0;">
                        <div class="header">
                            <h5>WBHSMS - CHO Koronadal</h5>
                        </div>
                        <div style="text-align: center; padding: 20px 0;">
                            <img src="<?php echo $assets_path; ?>/images/user-default.png" id="patientPhoto" alt="Patient Photo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #0077b6; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                            <h4 id="patientName" style="margin-top: 15px; color: var(--primary-dark); font-weight: 600;"></h4>
                            <p style="color: var(--primary); font-weight: 500; letter-spacing: 1px;"><i class="fas fa-id-badge" style="margin-right: 5px;"></i> Patient ID: <span id="patientId"></span></p>
                        </div>
                        <div class="info">
                            <p><strong><i class="fas fa-calendar-alt" style="color: var(--primary);"></i> Date of Birth:</strong> <span id="patientDob"></span></p>
                            <p><strong><i class="fas fa-venus-mars" style="color: var(--primary);"></i> Sex:</strong> <span id="patientSex"></span></p>
                            <p><strong><i class="fas fa-map-marker-alt" style="color: var(--primary);"></i> Barangay:</strong> <span id="patientBarangay"></span></p>
                            <p><strong><i class="fas fa-phone" style="color: var(--primary);"></i> Contact Number:</strong> <span id="patientContact"></span></p>
                        </div>
                        
                        <div class="header" style="margin-top: 20px;">
                            <h5>Emergency Contact</h5>
                        </div>
                        <div class="info">
                            <p><strong><i class="fas fa-user" style="color: var(--primary);"></i> Name:</strong> <span id="emergencyName"></span></p>
                            <p><strong><i class="fas fa-phone" style="color: var(--primary);"></i> Contact Number:</strong> <span id="emergencyContact"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-secondary" id="closeModalBtn">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="action-btn btn-primary" id="printIdCard">
                        <i class="fas fa-print"></i> Print ID Card
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            let currentPage = 1;
            let searchTimeout;
            
            function performSearch(page = 1) {
                const searchQuery = $('#searchInput').val();
                const patientId = $('#patientIdInput').val();
                const firstName = $('#firstNameInput').val();
                const lastName = $('#lastNameInput').val();
                const middleName = $('#middleNameInput').val();
                const birthday = $('#birthdayInput').val();
                const status = $('#statusFilter').val();
                
                const params = new URLSearchParams({
                    search: searchQuery,
                    patient_id: patientId,
                    first_name: firstName,
                    last_name: lastName,
                    middle_name: middleName,
                    birthday: birthday,
                    status: status,
                    page: page
                });
                
                $('#loader').show();
                
                fetch(`patient_records_management.php?${params}`)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTableBody = doc.querySelector('#patientTable tbody');
                        const newPagination = doc.querySelector('.pagination');
                        
                        if (newTableBody) {
                            $('#patientTable tbody').html(newTableBody.innerHTML);
                        }
                        
                        if (newPagination) {
                            $('.pagination').parent().html(newPagination.parentElement.innerHTML);
                        } else {
                            $('.pagination').parent().empty();
                        }
                        
                        // Update badge counts
                        const badges = doc.querySelectorAll('.total-count .badge');
                        if (badges.length >= 3) {
                            $('.total-count .badge').eq(0).text(badges[0].textContent);
                            $('.total-count .badge').eq(1).text(badges[1].textContent);
                            $('.total-count .badge').eq(2).text(badges[2].textContent);
                        }
                        
                        currentPage = page;
                        $('#loader').hide();
                        
                        // Re-bind event handlers for new elements
                        bindEventHandlers();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        $('#loader').hide();
                    });
            }
            
            function bindEventHandlers() {
                // View contact modal
                $('.view-contact').off('click').on('click', function() {
                    const data = $(this).data();
                    showContactModal(data);
                });
                
                // Pagination clicks
                $('.pagination a').off('click').on('click', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    if (page && !$(this).parent().hasClass('disabled') && !$(this).parent().hasClass('active')) {
                        performSearch(page);
                    }
                });
            }
            
            function showContactModal(data) {
                $('#patientPhoto').attr('src', data.photo || '<?php echo $assets_path; ?>/images/user-default.png');
                $('#patientName').text(data.name || 'N/A');
                $('#patientId').text(data.username || 'N/A');
                $('#patientDob').text(data.dob || 'N/A');
                $('#patientSex').text(data.sex || 'N/A');
                $('#patientContact').text(data.contact || 'N/A');
                $('#patientBarangay').text(data.barangay || 'N/A');
                $('#emergencyName').text(data.emergencyName || 'N/A');
                $('#emergencyContact').text(data.emergencyContact || 'N/A');
                
                $('#contactModal').addClass('show');
            }
            
            function closeModal() {
                $('#contactModal').removeClass('show');
            }
            
            // Debounced search
            function debouncedSearch() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => performSearch(1), 300);
            }
            
            // Search input events
            $('#searchInput, #patientIdInput, #firstNameInput, #lastNameInput, #middleNameInput, #birthdayInput').on('input', debouncedSearch);
            $('#statusFilter').on('change', debouncedSearch);
            
            // Clear filters
            $('#clearFilters').on('click', function() {
                $('#searchInput, #patientIdInput, #firstNameInput, #lastNameInput, #middleNameInput, #birthdayInput').val('');
                $('#statusFilter').val('');
                performSearch(1);
            });
            
            // Export dropdown
            $('#exportDropdown').on('click', function(e) {
                e.preventDefault();
                $('#exportMenu').toggle();
            });
            
            // Close modal events
            $('#closeContactModal, #closeModalBtn').on('click', closeModal);
            $('#contactModal').on('click', function(e) {
                if (e.target === this) closeModal();
            });
            
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') closeModal();
            });

            // Print ID Card functionality
            $('#printIdCard').on('click', function() {
                const patientName = $('#patientName').text();
                const patientId = $('#patientId').text();
                const patientDob = $('#patientDob').text();
                const patientSex = $('#patientSex').text();
                const patientContact = $('#patientContact').text();
                const patientBarangay = $('#patientBarangay').text();
                const emergencyName = $('#emergencyName').text();
                const emergencyContact = $('#emergencyContact').text();
                const photoSrc = $('#patientPhoto').attr('src');
                
                // Create print window
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                    <head>
                        <title>Patient ID Card - ${patientName}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .id-card { border: 2px solid #0077b6; border-radius: 10px; padding: 20px; max-width: 400px; margin: 0 auto; }
                            .header { background: #0077b6; color: white; text-align: center; padding: 10px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
                            .photo { text-align: center; margin-bottom: 15px; }
                            .photo img { width: 120px; height: 120px; border-radius: 50%; border: 3px solid #0077b6; }
                            .info p { margin: 8px 0; display: flex; justify-content: space-between; }
                            .info strong { color: #0077b6; }
                            .section-header { background: #0077b6; color: white; padding: 8px; margin: 15px -20px 10px -20px; text-align: center; font-weight: bold; }
                        </style>
                    </head>
                    <body>
                        <div class="id-card">
                            <div class="header">
                                <h3>WBHSMS - CHO Koronadal</h3>
                            </div>
                            <div class="photo">
                                <img src="${photoSrc}" alt="Patient Photo">
                                <h4>${patientName}</h4>
                                <p><strong>Patient ID: ${patientId}</strong></p>
                            </div>
                            <div class="info">
                                <p><strong>Date of Birth:</strong> <span>${patientDob}</span></p>
                                <p><strong>Sex:</strong> <span>${patientSex}</span></p>
                                <p><strong>Barangay:</strong> <span>${patientBarangay}</span></p>
                                <p><strong>Contact:</strong> <span>${patientContact}</span></p>
                            </div>
                            <div class="section-header">Emergency Contact</div>
                            <div class="info">
                                <p><strong>Name:</strong> <span>${emergencyName}</span></p>
                                <p><strong>Contact:</strong> <span>${emergencyContact}</span></p>
                            </div>
                        </div>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                
                setTimeout(() => {
                    printWindow.print();
                }, 250);
            });
            
            // Initialize event handlers
            bindEventHandlers();
        });
    </script>
</body>
</html>