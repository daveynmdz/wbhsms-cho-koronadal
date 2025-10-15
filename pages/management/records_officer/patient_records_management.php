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
$activePage = 'patient_records';

// Define role-based permissions - Records Officer specific
$canEdit = ($_SESSION['role'] === 'records_officer'); // Records Officer can edit patient records
$canView = ($_SESSION['role'] === 'records_officer');

if (!$canView) {
    $role = $_SESSION['role'];
    header("Location: ../../$role/dashboard.php");
    exit();
}

// Handle AJAX requests for search and filter
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$patientIdFilter = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';
$firstNameFilter = isset($_GET['first_name']) ? $_GET['first_name'] : '';
$lastNameFilter = isset($_GET['last_name']) ? $_GET['last_name'] : '';
$middleNameFilter = isset($_GET['middle_name']) ? $_GET['middle_name'] : '';
$birthdayFilter = isset($_GET['birthday']) ? $_GET['birthday'] : '';
$barangayFilter = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Count total records for pagination
$countSql = "SELECT COUNT(DISTINCT p.patient_id) as total 
             FROM patients p 
             LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
             WHERE 1=1";

$params = [];
$types = "";

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

if (!empty($barangayFilter)) {
    $countSql .= " AND p.barangay_id = ?";
    array_push($params, $barangayFilter);
    $types .= "i";
}

if (!empty($statusFilter)) {
    $countSql .= " AND p.status = ?";
    array_push($params, $statusFilter);
    $types .= "s";
}

$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Count active patients
$activeCountSql = "SELECT COUNT(DISTINCT p.patient_id) as active_count 
                   FROM patients p 
                   LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
                   WHERE p.status = 'active'";
$activeParams = [];
$activeTypes = "";

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

if (!empty($barangayFilter)) {
    $activeCountSql .= " AND p.barangay_id = ?";
    array_push($activeParams, $barangayFilter);
    $activeTypes .= "i";
}

$activeCountStmt = $conn->prepare($activeCountSql);
if (!empty($activeParams)) {
    $activeCountStmt->bind_param($activeTypes, ...$activeParams);
}
$activeCountStmt->execute();
$activeCountResult = $activeCountStmt->get_result();
$activePatients = $activeCountResult->fetch_assoc()['active_count'];

// Count inactive patients
$inactiveCountSql = "SELECT COUNT(DISTINCT p.patient_id) as inactive_count 
                     FROM patients p 
                     LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
                     WHERE p.status = 'inactive'";
$inactiveParams = [];
$inactiveTypes = "";

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

if (!empty($barangayFilter)) {
    $inactiveCountSql .= " AND p.barangay_id = ?";
    array_push($inactiveParams, $barangayFilter);
    $inactiveTypes .= "i";
}

$inactiveCountStmt = $conn->prepare($inactiveCountSql);
if (!empty($inactiveParams)) {
    $inactiveCountStmt->bind_param($inactiveTypes, ...$inactiveParams);
}
$inactiveCountStmt->execute();
$inactiveCountResult = $inactiveCountStmt->get_result();
$inactivePatients = $inactiveCountResult->fetch_assoc()['inactive_count'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get patient records
$sql = "SELECT p.patient_id, p.username, p.status, 
        CONCAT(p.last_name, ', ', p.first_name, 
               CASE WHEN p.middle_name IS NOT NULL AND p.middle_name != '' 
                    THEN CONCAT(' ', p.middle_name) 
                    ELSE '' END) as full_name,
        p.first_name, p.last_name, p.middle_name, p.date_of_birth, 
        p.gender, p.contact_number, p.email_address, 
        p.profile_photo, p.created_at, 
        b.barangay_name, b.barangay_id
        FROM patients p 
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
        WHERE 1=1";

// Apply the same filters for the main query
$params = [];
$types = "";

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

if (!empty($barangayFilter)) {
    $sql .= " AND p.barangay_id = ?";
    array_push($params, $barangayFilter);
    $types .= "i";
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
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all barangays for filter dropdown
$barangaySql = "SELECT barangay_id, barangay_name FROM barangay ORDER BY barangay_name";
$barangayResult = $conn->query($barangaySql);

// Handle status update (Records Officers can update patient status)
if ($_POST['action'] === 'update_status' && $canEdit) {
    $patient_id = intval($_POST['patient_id']);
    $new_status = $_POST['status'] === 'active' ? 'active' : 'inactive';
    
    $updateSql = "UPDATE patients SET status = ? WHERE patient_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $new_status, $patient_id);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Patient status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update patient status']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records Management - CHO Koronadal</title>
    
    <!-- External Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Custom Stylesheets -->
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    
    <style>
        /* Main Content Styles */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        /* Patient Records Specific Styles */
        .records-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .records-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .records-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
        }

        .stat-card.total { border-left-color: #3498db; }
        .stat-card.active { border-left-color: #27ae60; }
        .stat-card.inactive { border-left-color: #e74c3c; }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .stat-card.total .icon { color: #3498db; }
        .stat-card.active .icon { color: #27ae60; }
        .stat-card.inactive .icon { color: #e74c3c; }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .stat-card .label {
            color: #7f8c8d;
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Controls Section */
        .controls-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .search-filter-container {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: end;
        }

        .search-group {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #1e3c72;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 60, 114, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        /* Filters */
        .filters-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .filter-select, .filter-input {
            padding: 10px 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #1e3c72;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #e1e8ed;
            vertical-align: middle;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .profile-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1e3c72;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .patient-details h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .patient-details p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Status Badge */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge:hover {
            transform: scale(1.05);
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        /* Pagination */
        .pagination-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination {
            display: flex;
            gap: 5px;
        }

        .pagination a, .pagination .current {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination a {
            color: #1e3c72;
            border: 2px solid #e1e8ed;
        }

        .pagination a:hover {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }

        .pagination .current {
            background: #1e3c72;
            color: white;
            border: 2px solid #1e3c72;
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
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 30px;
        }

        .patient-detail-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 20px;
            align-items: start;
        }

        .patient-photo-section {
            text-align: center;
        }

        .patient-info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #1e3c72;
        }

        .info-group h4 {
            margin: 0 0 10px 0;
            color: #1e3c72;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .info-group p {
            margin: 5px 0;
            color: #2c3e50;
        }

        .info-group strong {
            color: #1e3c72;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .records-header h1 {
                font-size: 2rem;
            }

            .search-filter-container {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .filters-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .patient-detail-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .patient-info-section {
                grid-template-columns: 1fr;
            }

            .action-btns {
                flex-direction: column;
            }
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 10px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .empty-state p {
            margin: 0;
            font-size: 1.1rem;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include '../../../includes/sidebar_records_officer.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="records-header">
            <h1><i class="fas fa-user-injured"></i> Patient Records Management</h1>
            <p>Comprehensive patient records system for healthcare management</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="number"><?php echo number_format($totalRecords); ?></div>
                <div class="label">Total Patients</div>
            </div>
            
            <div class="stat-card active">
                <div class="icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="number"><?php echo number_format($activePatients); ?></div>
                <div class="label">Active Patients</div>
            </div>
            
            <div class="stat-card inactive">
                <div class="icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="number"><?php echo number_format($inactivePatients); ?></div>
                <div class="label">Inactive Patients</div>
            </div>
        </div>

        <!-- Search and Filter Controls -->
        <div class="controls-section">
            <div class="search-filter-container">
                <div class="search-group">
                    <input type="text" class="search-input" id="globalSearch" placeholder="Search patients by name, ID, barangay, or date...">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <button type="button" class="btn btn-secondary" id="toggleFilters">
                    <i class="fas fa-filter"></i> Advanced Filters
                </button>
                <button type="button" class="btn btn-primary" id="exportCsv">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>

            <!-- Advanced Filters -->
            <div class="filters-container" id="filtersContainer" style="display: none;">
                <div class="filter-group">
                    <label class="filter-label">Patient ID</label>
                    <input type="text" class="filter-input" id="patientIdFilter" placeholder="Enter Patient ID">
                </div>
                <div class="filter-group">
                    <label class="filter-label">First Name</label>
                    <input type="text" class="filter-input" id="firstNameFilter" placeholder="Enter first name">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Last Name</label>
                    <input type="text" class="filter-input" id="lastNameFilter" placeholder="Enter last name">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Middle Name</label>
                    <input type="text" class="filter-input" id="middleNameFilter" placeholder="Enter middle name">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Birthday</label>
                    <input type="date" class="filter-input" id="birthdayFilter">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Barangay</label>
                    <select class="filter-select" id="barangayFilter">
                        <option value="">All Barangays</option>
                        <?php while ($barangay = $barangayResult->fetch_assoc()): ?>
                            <option value="<?php echo $barangay['barangay_id']; ?>"><?php echo htmlspecialchars($barangay['barangay_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <button type="button" class="btn btn-secondary" id="clearFilters">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Patient Records Table -->
        <div class="table-container">
            <?php if ($result->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Patient ID</th>
                            <th>Contact Info</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Date Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($patient = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="patient-info">
                                        <img src="<?php echo $assets_path; ?>/images/user-default.png" class="profile-img" alt="Patient Photo">
                                        <div class="patient-details">
                                            <h4><?php echo htmlspecialchars($patient['full_name']); ?></h4>
                                            <p>
                                                <i class="fas fa-birthday-cake"></i>
                                                <?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?> 
                                                (<?php echo date_diff(date_create($patient['date_of_birth']), date_create('today'))->y; ?> years)
                                            </p>
                                            <p><i class="fas fa-venus-mars"></i> <?php echo ucfirst($patient['gender']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($patient['username']); ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($patient['contact_number'])): ?>
                                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['contact_number']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['email_address'])): ?>
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email_address']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($patient['barangay_name']); ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
                                        <button class="status-badge status-<?php echo $patient['status']; ?>" 
                                                onclick="toggleStatus(<?php echo $patient['patient_id']; ?>, '<?php echo $patient['status']; ?>')">
                                            <?php echo ucfirst($patient['status']); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="status-badge status-<?php echo $patient['status']; ?>">
                                            <?php echo ucfirst($patient['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($patient['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn btn-info btn-sm" 
                                                onclick="viewPatient(<?php echo $patient['patient_id']; ?>)"
                                                data-patient-id="<?php echo $patient['patient_id']; ?>"
                                                data-full-name="<?php echo htmlspecialchars($patient['full_name']); ?>"
                                                data-username="<?php echo htmlspecialchars($patient['username']); ?>"
                                                data-first-name="<?php echo htmlspecialchars($patient['first_name']); ?>"
                                                data-last-name="<?php echo htmlspecialchars($patient['last_name']); ?>"
                                                data-middle-name="<?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>"
                                                data-dob="<?php echo $patient['date_of_birth']; ?>"
                                                data-gender="<?php echo $patient['gender']; ?>"
                                                data-contact="<?php echo htmlspecialchars($patient['contact_number'] ?? ''); ?>"
                                                data-email="<?php echo htmlspecialchars($patient['email_address'] ?? ''); ?>"
                                                data-barangay="<?php echo htmlspecialchars($patient['barangay_name']); ?>"
                                                data-status="<?php echo $patient['status']; ?>"
                                                data-created="<?php echo $patient['created_at']; ?>"
                                                data-photo="<?php echo !empty($patient['profile_photo']) ? 'data:image/jpeg;base64,' . base64_encode($patient['profile_photo']) : $assets_path . '/images/user-default.png'; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Patients Found</h3>
                    <p>No patient records match your current search criteria.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo number_format($totalRecords); ?> results
                </div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Patient Details Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-injured"></i> Patient Details</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="patient-detail-grid">
                    <div class="patient-photo-section">
                        <img src="<?php echo $assets_path; ?>/images/user-default.png" id="patientPhoto" alt="Patient Photo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #1e3c72; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    </div>
                    <div class="patient-info-section">
                        <div class="info-group">
                            <h4><i class="fas fa-user"></i> Personal Information</h4>
                            <p><strong>Full Name:</strong> <span id="modalFullName"></span></p>
                            <p><strong>Patient ID:</strong> <span id="modalUsername"></span></p>
                            <p><strong>Date of Birth:</strong> <span id="modalDob"></span></p>
                            <p><strong>Age:</strong> <span id="modalAge"></span></p>
                            <p><strong>Gender:</strong> <span id="modalGender"></span></p>
                        </div>
                        <div class="info-group">
                            <h4><i class="fas fa-address-book"></i> Contact Information</h4>
                            <p><strong>Phone:</strong> <span id="modalContact"></span></p>
                            <p><strong>Email:</strong> <span id="modalEmail"></span></p>
                            <p><strong>Barangay:</strong> <span id="modalBarangay"></span></p>
                        </div>
                        <div class="info-group">
                            <h4><i class="fas fa-info-circle"></i> Account Information</h4>
                            <p><strong>Status:</strong> <span id="modalStatus" class="status-badge"></span></p>
                            <p><strong>Date Registered:</strong> <span id="modalCreated"></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Global variables
        let currentPage = <?php echo $page; ?>;
        let totalPages = <?php echo $totalPages; ?>;
        
        // Initialize page
        $(document).ready(function() {
            initializeEventListeners();
            setFilterValues();
        });

        // Initialize event listeners
        function initializeEventListeners() {
            // Global search
            $('#globalSearch').on('input', debounce(function() {
                applyFilters();
            }, 300));

            // Filter inputs
            $('.filter-input, .filter-select').on('change input', debounce(function() {
                applyFilters();
            }, 300));

            // Toggle filters
            $('#toggleFilters').on('click', function() {
                $('#filtersContainer').slideToggle();
                const icon = $(this).find('i');
                icon.toggleClass('fa-filter fa-filter-circle-xmark');
            });

            // Clear filters
            $('#clearFilters').on('click', function() {
                clearAllFilters();
            });

            // Export CSV
            $('#exportCsv').on('click', function() {
                exportToCSV();
            });

            // Modal close events
            $(document).on('click', function(e) {
                if (e.target.id === 'patientModal') {
                    closeModal();
                }
            });
        }

        // Set filter values from URL parameters
        function setFilterValues() {
            const params = new URLSearchParams(window.location.search);
            $('#globalSearch').val(params.get('search') || '');
            $('#patientIdFilter').val(params.get('patient_id') || '');
            $('#firstNameFilter').val(params.get('first_name') || '');
            $('#lastNameFilter').val(params.get('last_name') || '');
            $('#middleNameFilter').val(params.get('middle_name') || '');
            $('#birthdayFilter').val(params.get('birthday') || '');
            $('#barangayFilter').val(params.get('barangay') || '');
            $('#statusFilter').val(params.get('status') || '');
        }

        // Apply filters and reload page
        function applyFilters() {
            const params = new URLSearchParams();
            
            const search = $('#globalSearch').val().trim();
            if (search) params.set('search', search);
            
            const patientId = $('#patientIdFilter').val().trim();
            if (patientId) params.set('patient_id', patientId);
            
            const firstName = $('#firstNameFilter').val().trim();
            if (firstName) params.set('first_name', firstName);
            
            const lastName = $('#lastNameFilter').val().trim();
            if (lastName) params.set('last_name', lastName);
            
            const middleName = $('#middleNameFilter').val().trim();
            if (middleName) params.set('middle_name', middleName);
            
            const birthday = $('#birthdayFilter').val();
            if (birthday) params.set('birthday', birthday);
            
            const barangay = $('#barangayFilter').val();
            if (barangay) params.set('barangay', barangay);
            
            const status = $('#statusFilter').val();
            if (status) params.set('status', status);
            
            params.set('page', '1'); // Reset to first page
            
            const url = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }

        // Clear all filters
        function clearAllFilters() {
            $('#globalSearch, .filter-input').val('');
            $('.filter-select').val('');
            window.location.href = window.location.pathname;
        }

        // View patient details
        function viewPatient(patientId) {
            const btn = $(`button[data-patient-id="${patientId}"]`);
            
            console.log('View contact clicked for:', btn.data('full-name'));
            
            // Populate modal with patient data
            $('#modalFullName').text(btn.data('full-name'));
            $('#modalUsername').text(btn.data('username'));
            $('#modalDob').text(formatDate(btn.data('dob')));
            $('#modalAge').text(calculateAge(btn.data('dob')) + ' years');
            $('#modalGender').text(capitalizeFirst(btn.data('gender')));
            $('#modalContact').text(btn.data('contact') || 'Not provided');
            $('#modalEmail').text(btn.data('email') || 'Not provided');
            $('#modalBarangay').text(btn.data('barangay'));
            
            const status = btn.data('status');
            $('#modalStatus').text(capitalizeFirst(status)).removeClass().addClass(`status-badge status-${status}`);
            $('#modalCreated').text(formatDate(btn.data('created')));
            
            // Set patient photo
            $('#patientPhoto').attr('src', btn.data('photo') || '<?php echo $assets_path; ?>/images/user-default.png');
            
            // Show modal
            $('#patientModal').fadeIn();
        }

        // Close modal
        function closeModal() {
            console.log('Close contact modal clicked');
            $('#patientModal').fadeOut();
        }

        // Toggle patient status (for records officers)
        function toggleStatus(patientId, currentStatus) {
            <?php if ($canEdit): ?>
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                const confirmMessage = `Are you sure you want to change this patient's status to ${newStatus}?`;
                
                if (confirm(confirmMessage)) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            action: 'update_status',
                            patient_id: patientId,
                            status: newStatus
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while updating the status.');
                        }
                    });
                }
            <?php endif; ?>
        }

        // Export to CSV
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = window.location.pathname + '?' + params.toString();
        }

        // Utility functions
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }

        function calculateAge(birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            
            return age;
        }

        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
</body>
</html>