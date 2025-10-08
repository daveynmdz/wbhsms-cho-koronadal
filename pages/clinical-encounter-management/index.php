<?php
// index.php - Clinical Encounter Management Dashboard
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(dirname(__FILE__)));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check if role is authorized for clinical encounters
$authorized_roles = ['doctor', 'nurse', 'admin', 'records_officer', 'bhw', 'dho'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: ../dashboard.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_role = strtolower($_SESSION['role']);

// Get employee details for role-based filtering
$employee_details = null;
try {
    $stmt = $conn->prepare("
        SELECT e.*, r.role_name 
        FROM employees e 
        JOIN roles r ON e.role_id = r.role_id 
        WHERE e.employee_id = ?
    ");
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_details = $result->fetch_assoc();
    
    // Update session role if needed
    if ($employee_details && isset($employee_details['role_name'])) {
        $_SESSION['role'] = $employee_details['role_name'];
        $employee_role = strtolower($employee_details['role_name']);
    }
} catch (Exception $e) {
    // Continue without employee details
}

// Include sidebar component
$activePage = 'clinical_encounters';

// Pagination and filtering
$records_per_page = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$doctor_filter = $_GET['doctor'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$district_filter = $_GET['district'] ?? '';

// Build WHERE conditions with role-based access control
$where_conditions = ['1=1'];
$params = [];
$param_types = '';

// Role-based filtering with proper access control
switch ($employee_role) {
    case 'doctor':
    case 'nurse':
        // Doctor/Nurse: Show consultations assigned to them or where they were involved
        if (empty($search_query) && empty($doctor_filter)) {
            $where_conditions[] = "(c.attending_employee_id = ? OR EXISTS (
                SELECT 1 FROM vitals vt WHERE vt.visit_id = c.visit_id AND vt.taken_by = ?
            ))";
            $params[] = $employee_id;
            $params[] = $employee_id;
            $param_types .= 'ii';
        }
        break;
        
    case 'bhw':
        // BHW: Limited to patients from their assigned barangay
        if ($employee_details && isset($employee_details['assigned_barangay_id'])) {
            $where_conditions[] = "p.barangay_id = ?";
            $params[] = $employee_details['assigned_barangay_id'];
            $param_types .= 'i';
        } else {
            // If no assigned barangay, show no records
            $where_conditions[] = "1=0";
        }
        break;
        
    case 'dho':
        // DHO: Limited to patients from their assigned district
        if ($employee_details && isset($employee_details['assigned_district_id'])) {
            $where_conditions[] = "b.district_id = ?";
            $params[] = $employee_details['assigned_district_id'];
            $param_types .= 'i';
        } else {
            // If no assigned district, show no records
            $where_conditions[] = "1=0";
        }
        break;
        
    case 'admin':
        // Admin: Full access to all consultations (no additional filter)
        break;
        
    case 'records_officer':
        // Records Officer: Read-only access to all consultations (no additional filter)
        break;
        
    default:
        // Unknown role: No access
        $where_conditions[] = "1=0";
        break;
}

if (!empty($search_query)) {
    $where_conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR p.username LIKE ?)";
    $search_term = "%$search_query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "c.consultation_status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(c.consultation_date) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(c.consultation_date) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

if (!empty($doctor_filter)) {
    $where_conditions[] = "c.attending_employee_id = ?";
    $params[] = $doctor_filter;
    $param_types .= 'i';
}

if (!empty($barangay_filter)) {
    $where_conditions[] = "p.barangay_id = ?";
    $params[] = $barangay_filter;
    $param_types .= 'i';
}

if (!empty($district_filter)) {
    $where_conditions[] = "b.district_id = ?";
    $params[] = $district_filter;
    $param_types .= 'i';
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM consultations c
    JOIN patients p ON c.patient_id = p.patient_id
    JOIN visits v ON c.visit_id = v.visit_id
    LEFT JOIN employees d ON c.attending_employee_id = d.employee_id
    LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
    LEFT JOIN districts dist ON b.district_id = dist.district_id
    WHERE $where_clause
";

$total_records = 0;
if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_records = $result->fetch_assoc()['total'];
} else {
    $result = $conn->query($count_sql);
    $total_records = $result->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $records_per_page);

// Get clinical encounters with pagination
$sql = "
    SELECT c.consultation_id as encounter_id, c.patient_id, c.visit_id, c.chief_complaint, 
           c.diagnosis, c.consultation_status as status, c.consultation_date, c.created_at, c.updated_at,
           p.first_name, p.last_name, p.username as patient_id_display,
           TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age, p.sex,
           d.first_name as doctor_first_name, d.last_name as doctor_last_name,
           b.barangay_name, dist.district_name,
           v.visit_type, v.visit_purpose,
           (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.consultation_id) as prescription_count,
           (SELECT COUNT(*) FROM lab_orders WHERE consultation_id = c.consultation_id) as lab_test_count,
           (SELECT COUNT(*) FROM referrals WHERE consultation_id = c.consultation_id) as referral_count
    FROM consultations c
    JOIN patients p ON c.patient_id = p.patient_id
    JOIN visits v ON c.visit_id = v.visit_id
    LEFT JOIN employees d ON c.attending_employee_id = d.employee_id
    LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
    LEFT JOIN districts dist ON b.district_id = dist.district_id
    WHERE $where_clause
    ORDER BY c.consultation_date DESC, c.created_at DESC
    LIMIT ? OFFSET ?
";

$encounters = [];
$limit_params = $params;
$limit_params[] = $records_per_page;
$limit_params[] = $offset;
$limit_param_types = $param_types . 'ii';

try {
    if (!empty($limit_params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($limit_param_types, ...$limit_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $encounters = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $result = $conn->query($sql);
        $encounters = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $encounters = [];
    // You can log the error here if needed
}

// Get available doctors for filter
$doctors = [];
try {
    $stmt = $conn->prepare("
        SELECT e.employee_id, e.first_name, e.last_name 
        FROM employees e 
        JOIN roles r ON e.role_id = r.role_id 
        WHERE r.role_name = 'doctor' AND e.status = 'active' 
        ORDER BY e.first_name, e.last_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $doctors = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for doctors
}

// Get available barangays for filter
$barangays = [];
try {
    $stmt = $conn->prepare("SELECT barangay_id, barangay_name FROM barangay ORDER BY barangay_name");
    $stmt->execute();
    $result = $stmt->get_result();
    $barangays = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for barangays
}

// Get available districts for filter
$districts = [];
try {
    $stmt = $conn->prepare("SELECT district_id, district_name FROM districts ORDER BY district_name");
    $stmt->execute();
    $result = $stmt->get_result();
    $districts = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for districts
}

// Get encounter statistics with role-based filtering
$stats = [
    'total_encounters' => 0,
    'completed_today' => 0,
    'follow_ups_needed' => 0,
    'referred_cases' => 0
];

// Build role-based stats filter
$stats_where = ['consultation_status != \'cancelled\''];
$stats_params = [];
$stats_param_types = '';

switch ($employee_role) {
    case 'doctor':
    case 'nurse':
        $stats_where[] = "c.attending_employee_id = ?";
        $stats_params[] = $employee_id;
        $stats_param_types .= 'i';
        break;
    case 'bhw':
    case 'dho':
        // Note: BHW/DHO filtering would need employee-barangay/district assignment table
        // For now, they see all statistics (like admin)
        break;
}

$stats_where_clause = implode(' AND ', $stats_where);

try {
    // Total consultations
    $sql = "SELECT COUNT(*) as total FROM consultations c 
            JOIN patients p ON c.patient_id = p.patient_id 
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
            WHERE $stats_where_clause";
    if (!empty($stats_params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($stats_param_types, ...$stats_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_encounters'] = $result->fetch_assoc()['total'];
    } else {
        $result = $conn->query($sql);
        $stats['total_encounters'] = $result->fetch_assoc()['total'];
    }

    // Completed today
    $completed_where = $stats_where;
    $completed_where[] = "consultation_status = 'completed'";
    $completed_where[] = "DATE(c.updated_at) = CURDATE()";
    $completed_where_clause = implode(' AND ', $completed_where);
    
    $sql = "SELECT COUNT(*) as total FROM consultations c 
            JOIN patients p ON c.patient_id = p.patient_id 
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
            WHERE $completed_where_clause";
    if (!empty($stats_params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($stats_param_types, ...$stats_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['completed_today'] = $result->fetch_assoc()['total'];
    } else {
        $result = $conn->query($sql);
        $stats['completed_today'] = $result->fetch_assoc()['total'];
    }

    // Follow-ups needed
    $followup_where = $stats_where;
    $followup_where[] = "consultation_status = 'awaiting_followup'";
    $followup_where_clause = implode(' AND ', $followup_where);
    
    $sql = "SELECT COUNT(*) as total FROM consultations c 
            JOIN patients p ON c.patient_id = p.patient_id 
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
            WHERE $followup_where_clause";
    if (!empty($stats_params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($stats_param_types, ...$stats_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['follow_ups_needed'] = $result->fetch_assoc()['total'];
    } else {
        $result = $conn->query($sql);
        $stats['follow_ups_needed'] = $result->fetch_assoc()['total'];
    }

    // Referred cases
    $sql = "SELECT COUNT(DISTINCT c.consultation_id) as total FROM consultations c 
            JOIN patients p ON c.patient_id = p.patient_id 
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
            JOIN referrals r ON c.consultation_id = r.consultation_id 
            WHERE $stats_where_clause";
    if (!empty($stats_params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($stats_param_types, ...$stats_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['referred_cases'] = $result->fetch_assoc()['total'];
    } else {
        $result = $conn->query($sql);
        $stats['referred_cases'] = $result->fetch_assoc()['total'];
    }
} catch (Exception $e) {
    // Keep default values
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Clinical Encounter Management | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../assets/css/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-card.total { border-left-color: #0077b6; }
        .stat-card.completed { border-left-color: #28a745; }
        .stat-card.follow-up { border-left-color: #dc3545; }
        .stat-card.referred { border-left-color: #6f42c1; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        .stat-card.total .stat-icon { color: #0077b6; }
        .stat-card.completed .stat-icon { color: #28a745; }
        .stat-card.follow-up .stat-icon { color: #dc3545; }
        .stat-card.referred .stat-icon { color: #6f42c1; }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .filters-container {
            background: white;
            border-radius: 12px;
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

        .encounter-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-new-encounter {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-new-encounter:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .encounters-table {
            width: 100%;
            border-collapse: collapse;
        }

        .encounters-table th,
        .encounters-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .encounters-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .encounters-table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-ongoing {
            background: #fff3cd;
            color: #856404;
        }

        .status-awaiting_lab_results {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-awaiting_followup {
            background: #f8d7da;
            color: #721c24;
        }

        .status-cancelled {
            background: #d1ecf1;
            color: #0c5460;
        }

        .patient-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .patient-name {
            font-weight: 600;
            color: #0077b6;
        }

        .patient-details {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .encounter-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-view {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-edit {
            background: #fff3e0;
            color: #f57c00;
        }

        .btn-view:hover {
            background: #1976d2;
            color: white;
        }

        .btn-edit:hover {
            background: #f57c00;
            color: white;
        }

        .encounter-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            color: #0077b6;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #0077b6;
            color: white;
        }

        .pagination .current {
            background: #0077b6;
            color: white;
            border-color: #0077b6;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .encounters-table {
                font-size: 0.875rem;
            }

            .encounters-table th,
            .encounters-table td {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="homepage">
        <?php include '../../includes/sidebar_' . $employee_role . '.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <h1><i class="fas fa-stethoscope"></i> Clinical Encounter Management</h1>
                    <p>Manage patient consultations, diagnoses, and clinical documentation</p>
                    
                    <?php
                    $scope_message = '';
                    switch ($employee_role) {
                        case 'bhw':
                            $barangay_name = $employee_details['barangay_id'] ?? 'your assigned barangay';
                            $scope_message = "You are viewing consultations for patients in your assigned barangay.";
                            break;
                        case 'dho':
                            $district_name = $employee_details['district_id'] ?? 'your assigned district';
                            $scope_message = "You are viewing consultations for patients in your assigned district.";
                            break;
                        case 'doctor':
                        case 'nurse':
                            $scope_message = "You are viewing consultations assigned to you. Use search to view other consultations.";
                            break;
                        case 'admin':
                        case 'records_officer':
                            $scope_message = "You have access to view all consultations system-wide.";
                            break;
                    }
                    ?>
                    <?php if ($scope_message): ?>
                        <div style="background: #e3f2fd; padding: 0.75rem; border-radius: 6px; margin-top: 1rem; font-size: 0.9rem; color: #1976d2;">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($scope_message) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($stats['total_encounters']) ?></div>
                        <div class="stat-label">Total Encounters</div>
                    </div>

                    <div class="stat-card completed">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($stats['completed_today']) ?></div>
                        <div class="stat-label">Completed Today</div>
                    </div>

                    <div class="stat-card follow-up">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($stats['follow_ups_needed']) ?></div>
                        <div class="stat-label">Follow-ups Needed</div>
                    </div>

                    <div class="stat-card referred">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-share-alt"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($stats['referred_cases']) ?></div>
                        <div class="stat-label">Referred Cases</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-container">
                    <h3><i class="fas fa-filter"></i> Filter Encounters</h3>
                    <form method="GET" class="filters-grid">
                        <div class="form-group">
                            <label for="search">Search Patient</label>
                            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>"
                                placeholder="Name, Patient ID...">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="ongoing" <?= $status_filter === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="awaiting_lab_results" <?= $status_filter === 'awaiting_lab_results' ? 'selected' : '' ?>>Awaiting Lab Results</option>
                                <option value="awaiting_followup" <?= $status_filter === 'awaiting_followup' ? 'selected' : '' ?>>Awaiting Follow-up</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="doctor">Doctor</label>
                            <select id="doctor" name="doctor">
                                <option value="">All Doctors</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?= $doctor['employee_id'] ?>" <?= $doctor_filter == $doctor['employee_id'] ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (in_array($employee_role, ['admin', 'records_officer', 'dho'])): ?>
                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?= $barangay['barangay_id'] ?>" <?= $barangay_filter == $barangay['barangay_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($barangay['barangay_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array($employee_role, ['admin', 'records_officer'])): ?>
                        <div class="form-group">
                            <label for="district">District</label>
                            <select id="district" name="district">
                                <option value="">All Districts</option>
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?= $district['district_id'] ?>" <?= $district_filter == $district['district_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($district['district_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Encounters Table -->
                <div class="encounter-table">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-list"></i>
                            Clinical Encounters (<?= number_format($total_records) ?> total)
                        </div>
                        <a href="consultation.php" class="btn-new-encounter">
                            <i class="fas fa-plus"></i> New Consultation
                        </a>
                    </div>

                    <div class="table-responsive">
                        <?php if (!empty($encounters)): ?>
                            <table class="encounters-table">
                                <thead>
                                    <tr>
                                        <th>Consultation Date</th>
                                        <th>Patient Name</th>
                                        <th>Doctor Name</th>
                                        <th>Chief Complaint</th>
                                        <th>Diagnosis</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($encounters as $encounter): ?>
                                        <tr>
                                            <td>
                                                <div><strong><?= date('M j, Y', strtotime($encounter['consultation_date'])) ?></strong></div>
                                                <div style="font-size: 0.8rem; color: #6c757d;">
                                                    <?= date('g:i A', strtotime($encounter['consultation_date'])) ?>
                                                </div>
                                                <?php if ($encounter['visit_type']): ?>
                                                    <div style="font-size: 0.75rem; color: #6c757d; margin-top: 0.25rem;">
                                                        <?= htmlspecialchars(ucwords($encounter['visit_type'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="patient-info">
                                                    <div class="patient-name">
                                                        <?= htmlspecialchars($encounter['first_name'] . ' ' . $encounter['last_name']) ?>
                                                    </div>
                                                    <div class="patient-details">
                                                        ID: <?= htmlspecialchars($encounter['patient_id_display']) ?> | 
                                                        <?= htmlspecialchars($encounter['age']) ?>y/o <?= htmlspecialchars($encounter['sex']) ?>
                                                    </div>
                                                    <?php if ($encounter['barangay_name'] && in_array($employee_role, ['admin', 'records_officer', 'dho'])): ?>
                                                        <div style="font-size: 0.75rem; color: #6c757d;">
                                                            <?= htmlspecialchars($encounter['barangay_name']) ?>
                                                            <?php if ($encounter['district_name'] && in_array($employee_role, ['admin', 'records_officer'])): ?>
                                                                , <?= htmlspecialchars($encounter['district_name']) ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($encounter['doctor_first_name']): ?>
                                                    <div><strong>Dr. <?= htmlspecialchars($encounter['doctor_first_name'] . ' ' . $encounter['doctor_last_name']) ?></strong></div>
                                                <?php else: ?>
                                                    <em>Not assigned</em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div title="<?= htmlspecialchars($encounter['chief_complaint']) ?>">
                                                    <?= htmlspecialchars(substr($encounter['chief_complaint'], 0, 50)) ?>
                                                    <?= strlen($encounter['chief_complaint']) > 50 ? '...' : '' ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div title="<?= htmlspecialchars($encounter['diagnosis'] ?? 'No diagnosis yet') ?>">
                                                    <?= $encounter['diagnosis'] ? htmlspecialchars(substr($encounter['diagnosis'], 0, 40)) : '<em>Pending</em>' ?>
                                                    <?= ($encounter['diagnosis'] && strlen($encounter['diagnosis']) > 40) ? '...' : '' ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= htmlspecialchars($encounter['status']) ?>">
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $encounter['status']))) ?>
                                                </span>
                                                <?php if ($encounter['prescription_count'] > 0 || $encounter['lab_test_count'] > 0 || $encounter['referral_count'] > 0): ?>
                                                    <div class="encounter-stats">
                                                        <?php if ($encounter['prescription_count'] > 0): ?>
                                                            <div class="stat-item">
                                                                <i class="fas fa-pills"></i>
                                                                <?= $encounter['prescription_count'] ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($encounter['lab_test_count'] > 0): ?>
                                                            <div class="stat-item">
                                                                <i class="fas fa-vial"></i>
                                                                <?= $encounter['lab_test_count'] ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($encounter['referral_count'] > 0): ?>
                                                            <div class="stat-item">
                                                                <i class="fas fa-share-alt"></i>
                                                                <?= $encounter['referral_count'] ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="encounter-actions">
                                                    <a href="view_consultation.php?id=<?= $encounter['encounter_id'] ?>" 
                                                       class="action-btn btn-view" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (in_array($employee_role, ['doctor', 'admin', 'records_officer']) || 
                                                              ($encounter['status'] == 'ongoing' && $employee_role == 'nurse')): ?>
                                                        <a href="edit_consultation.php?id=<?= $encounter['encounter_id'] ?>" 
                                                           class="action-btn btn-edit" title="Edit Consultation">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-stethoscope"></i>
                                <h3>No Clinical Encounters Found</h3>
                                <p>No consultations match your current filters.</p>
                                <a href="consultation.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create New Consultation
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>