<?php
// dashboard_records_officer.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not a records officer, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'records_officer') {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// -------------------- Data bootstrap (Records Officer Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'pending_records' => 0,
        'records_processed_today' => 0,
        'total_patient_records' => 0,
        'pending_requests' => 0,
        'archived_records' => 0,
        'data_quality_issues' => 0
    ],
    'pending_records' => [],
    'recent_activities' => [],
    'record_requests' => [],
    'system_alerts' => []
];

// Get records officer info from employees table
try {
    $stmt = $pdo->prepare('SELECT first_name, middle_name, last_name, employee_number, role FROM employees WHERE employee_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $full_name = $row['first_name'];
        if (!empty($row['middle_name'])) $full_name .= ' ' . $row['middle_name'];
        $full_name .= ' ' . $row['last_name'];
        $defaults['name'] = trim($full_name);
        $defaults['employee_number'] = $row['employee_number'];
        $defaults['role'] = $row['role'];
    }
} catch (PDOException $e) {
    error_log("Records officer dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Pending Records
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM medical_records WHERE status = "pending" AND assigned_officer_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_records'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Records Processed Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM medical_records WHERE DATE(updated_date) = ? AND assigned_officer_id = ? AND status = "completed"');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['records_processed_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Total Patient Records
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM patients WHERE status = "active"');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['total_patient_records'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Pending Requests
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM record_requests WHERE status = "pending" AND assigned_officer_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_requests'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Archived Records
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM medical_records WHERE status = "archived" AND assigned_officer_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['archived_records'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Data Quality Issues
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM data_quality_issues WHERE status = "open" AND assigned_officer_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['data_quality_issues'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Pending Records
try {
    $stmt = $pdo->prepare('
        SELECT mr.record_id, mr.record_type, mr.priority, mr.created_date,
               p.first_name, p.last_name, p.patient_id, mr.description
        FROM medical_records mr 
        JOIN patients p ON mr.patient_id = p.patient_id 
        WHERE mr.status = "pending" AND mr.assigned_officer_id = ? 
        ORDER BY mr.priority DESC, mr.created_date ASC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['pending_records'][] = [
            'record_id' => $row['record_id'],
            'record_type' => $row['record_type'] ?? 'Medical Record',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'description' => $row['description'] ?? 'Record processing',
            'priority' => $row['priority'] ?? 'normal',
            'created_date' => date('M d, Y', strtotime($row['created_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['pending_records'] = [
        ['record_id' => 'R001', 'record_type' => 'Medical History', 'patient_name' => 'Sample Patient', 'patient_id' => 'P001', 'description' => 'Complete medical history documentation', 'priority' => 'high', 'created_date' => 'Sep 20, 2025'],
        ['record_id' => 'R002', 'record_type' => 'Lab Results', 'patient_name' => 'Test Patient', 'patient_id' => 'P002', 'description' => 'Laboratory results filing', 'priority' => 'normal', 'created_date' => 'Sep 19, 2025']
    ];
}

// Recent Activities
try {
    $stmt = $pdo->prepare('
        SELECT ra.activity_type, ra.description, ra.created_date,
               p.first_name, p.last_name, p.patient_id
        FROM record_activities ra 
        LEFT JOIN patients p ON ra.patient_id = p.patient_id 
        WHERE ra.officer_id = ? 
        ORDER BY ra.created_date DESC 
        LIMIT 6
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['recent_activities'][] = [
            'activity_type' => $row['activity_type'] ?? 'Record Update',
            'description' => $row['description'] ?? 'Activity performed',
            'patient_name' => $row['patient_id'] ? trim($row['first_name'] . ' ' . $row['last_name']) : 'System',
            'patient_id' => $row['patient_id'] ?? '-',
            'created_date' => date('M d, Y H:i', strtotime($row['created_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['recent_activities'] = [
        ['activity_type' => 'File Update', 'description' => 'Updated patient medical history', 'patient_name' => 'John Doe', 'patient_id' => 'P001', 'created_date' => 'Sep 21, 2025 09:30'],
        ['activity_type' => 'Record Archive', 'description' => 'Archived completed patient records', 'patient_name' => 'Jane Smith', 'patient_id' => 'P002', 'created_date' => 'Sep 21, 2025 08:45'],
        ['activity_type' => 'Data Entry', 'description' => 'Entered new patient information', 'patient_name' => 'Mike Johnson', 'patient_id' => 'P003', 'created_date' => 'Sep 21, 2025 08:15']
    ];
}

// Record Requests
try {
    $stmt = $pdo->prepare('
        SELECT rr.request_id, rr.request_type, rr.requested_date, rr.urgency,
               p.first_name, p.last_name, p.patient_id, rr.requested_by
        FROM record_requests rr 
        JOIN patients p ON rr.patient_id = p.patient_id 
        WHERE rr.status = "pending" AND rr.assigned_officer_id = ? 
        ORDER BY rr.urgency DESC, rr.requested_date ASC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['record_requests'][] = [
            'request_id' => $row['request_id'],
            'request_type' => $row['request_type'] ?? 'Record Request',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'requested_by' => $row['requested_by'] ?? 'Unknown',
            'urgency' => $row['urgency'] ?? 'normal',
            'requested_date' => date('M d, Y', strtotime($row['requested_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['record_requests'] = [
        ['request_id' => 'REQ001', 'request_type' => 'Medical Certificate', 'patient_name' => 'Alice Brown', 'patient_id' => 'P004', 'requested_by' => 'Dr. Santos', 'urgency' => 'high', 'requested_date' => 'Sep 21, 2025'],
        ['request_id' => 'REQ002', 'request_type' => 'Lab Report Copy', 'patient_name' => 'Bob Wilson', 'patient_id' => 'P005', 'requested_by' => 'Patient', 'urgency' => 'normal', 'requested_date' => 'Sep 20, 2025']
    ];
}

// System Alerts
try {
    $stmt = $pdo->prepare('
        SELECT sa.alert_type, sa.message, sa.created_at, sa.priority
        FROM system_alerts sa 
        WHERE sa.target_role = "records_officer" AND sa.status = "active" 
        ORDER BY sa.priority DESC, sa.created_at DESC 
        LIMIT 4
    ');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['system_alerts'][] = [
            'alert_type' => $row['alert_type'] ?? 'general',
            'message' => $row['message'] ?? '',
            'priority' => $row['priority'] ?? 'normal',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default alerts
    $defaults['system_alerts'] = [
        ['alert_type' => 'backup', 'message' => 'Weekly records backup completed successfully', 'priority' => 'normal', 'date' => date('m/d/Y H:i')],
        ['alert_type' => 'maintenance', 'message' => 'Scheduled system maintenance tonight at 11 PM', 'priority' => 'normal', 'date' => date('m/d/Y H:i')],
        ['alert_type' => 'storage', 'message' => 'Archive storage 85% full - cleanup recommended', 'priority' => 'high', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Records Officer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #6f42c1;
            --primary-dark: #5a32a3;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .content-wrapper {
            margin-left: var(--sidebar-width, 260px);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Welcome Header */
        .welcome-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 300;
            line-height: 1.2;
        }

        .welcome-header .subtitle {
            margin-top: 0.5rem;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .welcome-header h1 {
                font-size: 1.8rem;
            }
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
        }

        .stat-card.pending { --card-color: #ffc107; }
        .stat-card.processed { --card-color: #28a745; }
        .stat-card.total { --card-color: #007bff; }
        .stat-card.requests { --card-color: #fd7e14; }
        .stat-card.archived { --card-color: #6c757d; }
        .stat-card.quality { --card-color: #dc3545; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--card-color, var(--primary));
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Quick Actions */
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color, var(--primary));
            transition: var(--transition);
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
        }

        .action-card:hover::before {
            width: 8px;
        }

        .action-card.purple { --card-color: #6f42c1; }
        .action-card.blue { --card-color: #007bff; }
        .action-card.green { --card-color: #28a745; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.teal { --card-color: #17a2b8; }
        .action-card.red { --card-color: #dc3545; }

        .action-card .icon {
            font-size: 2.5rem;
            color: var(--card-color, var(--primary));
            margin-bottom: 1rem;
            display: block;
        }

        .action-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-card p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        /* Info Layout */
        .info-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        @media (max-width: 1200px) {
            .info-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Card Sections */
        .card-section {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-more-btn {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .view-more-btn:hover {
            color: var(--primary-dark);
            text-decoration: none;
        }

        /* Tables */
        .table-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--border);
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table th,
        .records-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .records-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .records-table td {
            color: var(--secondary);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-urgent {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-high {
            background: #fff3cd;
            color: #856404;
        }

        .priority-normal {
            background: #d1ecf1;
            color: #0c5460;
        }

        .urgency-urgent {
            background: #f8d7da;
            color: #721c24;
        }

        .urgency-high {
            background: #fff3cd;
            color: #856404;
        }

        .urgency-normal {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-backup {
            background: #d4edda;
            color: #155724;
        }

        .alert-maintenance {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-storage {
            background: #fff3cd;
            color: #856404;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Record Item */
        .record-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .record-name {
            font-weight: 600;
            color: var(--dark);
        }

        .record-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Activity Item */
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid #28a745;
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .activity-name {
            font-weight: 600;
            color: var(--dark);
        }

        .activity-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include '../../includes/sidebar_records_officer.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                Records Management Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Records Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_records']); ?></div>
                <div class="stat-label">Pending Records</div>
            </div>
            <div class="stat-card processed">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['records_processed_today']); ?></div>
                <div class="stat-label">Processed Today</div>
            </div>
            <div class="stat-card total">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_patient_records']); ?></div>
                <div class="stat-label">Total Patient Records</div>
            </div>
            <div class="stat-card requests">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-file-medical"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_requests']); ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-card archived">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-archive"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['archived_records']); ?></div>
                <div class="stat-label">Archived Records</div>
            </div>
            <div class="stat-card quality">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['data_quality_issues']); ?></div>
                <div class="stat-label">Data Quality Issues</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="../records/new_patient_record.php" class="action-card purple">
                <i class="fas fa-user-plus icon"></i>
                <h3>New Patient Record</h3>
                <p>Create new patient medical record file</p>
            </a>
            <a href="../records/record_search.php" class="action-card blue">
                <i class="fas fa-search icon"></i>
                <h3>Search Records</h3>
                <p>Find and retrieve patient medical records</p>
            </a>
            <a href="../records/data_entry.php" class="action-card green">
                <i class="fas fa-keyboard icon"></i>
                <h3>Data Entry</h3>
                <p>Input and update patient information</p>
            </a>
            <a href="../records/record_archive.php" class="action-card orange">
                <i class="fas fa-archive icon"></i>
                <h3>Archive Records</h3>
                <p>Archive completed and old medical records</p>
            </a>
            <a href="../records/quality_control.php" class="action-card teal">
                <i class="fas fa-shield-alt icon"></i>
                <h3>Quality Control</h3>
                <p>Review and validate record accuracy</p>
            </a>
            <a href="../records/reports.php" class="action-card red">
                <i class="fas fa-chart-bar icon"></i>
                <h3>Generate Reports</h3>
                <p>Create statistical and compliance reports</p>
            </a>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Pending Records -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-clock"></i> Pending Records</h3>
                        <a href="../records/pending_records.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['pending_records'])): ?>
                        <div class="table-wrapper">
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Patient</th>
                                        <th>Priority</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['pending_records'] as $record): ?>
                                        <tr>
                                            <td>
                                                <div class="record-name"><?php echo htmlspecialchars($record['record_type']); ?></div>
                                                <small><?php echo htmlspecialchars($record['description']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($record['patient_name']); ?></div>
                                                <small><?php echo htmlspecialchars($record['patient_id']); ?></small>
                                            </td>
                                            <td><span class="status-badge priority-<?php echo $record['priority']; ?>"><?php echo ucfirst($record['priority']); ?></span></td>
                                            <td><?php echo htmlspecialchars($record['created_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending records at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-history"></i> Recent Activities</h3>
                        <a href="../records/activity_log.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['recent_activities'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['recent_activities'] as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-name"><?php echo htmlspecialchars($activity['activity_type']); ?></div>
                                    <div class="activity-details">
                                        <?php echo htmlspecialchars($activity['description']); ?><br>
                                        Patient: <?php echo htmlspecialchars($activity['patient_name']); ?> • 
                                        <?php echo htmlspecialchars($activity['created_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Record Requests -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-file-medical"></i> Record Requests</h3>
                        <a href="../records/record_requests.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['record_requests'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['record_requests'] as $request): ?>
                                <div class="record-item">
                                    <div class="record-name">
                                        <?php echo htmlspecialchars($request['request_type']); ?>
                                        <span class="status-badge urgency-<?php echo $request['urgency']; ?>"><?php echo ucfirst($request['urgency']); ?></span>
                                    </div>
                                    <div class="record-details">
                                        Patient: <?php echo htmlspecialchars($request['patient_name']); ?> • 
                                        Requested by: <?php echo htmlspecialchars($request['requested_by']); ?> • 
                                        Date: <?php echo htmlspecialchars($request['requested_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-medical"></i>
                            <p>No pending record requests</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- System Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-bell"></i> System Alerts</h3>
                        <a href="../records/system_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['system_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['system_alerts'] as $alert): ?>
                                <div class="record-item">
                                    <div class="record-name">
                                        System Alert
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </div>
                                    <div class="record-details">
                                        <?php echo htmlspecialchars($alert['message']); ?><br>
                                        <small><?php echo htmlspecialchars($alert['date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No system alerts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</body>

</html>
