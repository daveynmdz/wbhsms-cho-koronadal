<?php
// dashboard_records_officer.php
// Using the same approach as admin dashboard for consistency
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Authentication check - refactored to eliminate redirect loops
// Check 1: Is the user logged in at all?
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    // User is not logged in - redirect to login
    error_log('Records Officer Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'records_officer') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to records officer dashboard - User: ' . $_SESSION['employee_id'] . ' with role: ' . 
              ($_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}


// Log session data for debugging
error_log('Records Officer Dashboard - Session Data: ' . print_r($_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/staff_assignment.php';

// Debug connection status
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Check staff assignment for today (non-blocking)
$assignment = null;
$assignment_warning = '';
try {
    $assignment = getStaffAssignment($employee_id);
    if (!$assignment) {
        // Not assigned today, but allow access with warning
        error_log('Records Officer Dashboard: No active staff assignment for today - allowing access with warning.');
        $assignment_warning = 'You are not assigned to any station today. Some queue management features may be limited. Please contact the administrator if you need station access.';
    }
} catch (Exception $e) {
    // Staff assignment function failed, log error but continue
    error_log('Records Officer Dashboard: Staff assignment check failed: ' . $e->getMessage());
    $assignment_warning = 'Unable to verify station assignment. Some features may be limited.';
}

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
    <!-- Use absolute paths for all styles -->
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/sidebar.css">
    <style>
        :root {
            --primary: #6f42c1;
            --primary-light: #8e68d9;
            --primary-dark: #5a32a3;
            --secondary: #6c757d;
            --secondary-light: #adb5bd;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --light-hover: #e2e6ea;
            --dark: #343a40;
            --white: #ffffff;
            --border: #dee2e6;
            --border-light: #f1f1f1;
            --shadow-sm: 0 .125rem .25rem rgba(0,0,0,.075);
            --shadow: 0 .5rem 1rem rgba(0,0,0,.08);
            --shadow-lg: 0 1rem 3rem rgba(0,0,0,.1);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
            --card-hover-y: -5px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5eeff 0%, #eee2ff 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: var(--dark);
        }

        /* Content Wrapper */
        .content-wrapper {
            margin-left: var(--sidebar-width, 280px);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1.5rem;
                margin-top: 70px;
            }
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-message h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin: 0;
            color: var(--primary-dark);
            line-height: 1.2;
        }

        .welcome-message p {
            margin: 0.5rem 0 0;
            color: var(--secondary);
            font-size: 1rem;
        }

        .dashboard-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background: var(--dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Info Card */
        .info-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .info-card h2 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-card p {
            margin: 0;
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .welcome-message h1 {
                font-size: 1.8rem;
            }
            
            .dashboard-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 2.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            border: 1px solid var(--border-light);
            overflow: hidden;
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 4px;
            width: 100%;
            background: var(--card-color, var(--primary));
        }

        .stat-card:hover {
            transform: translateY(var(--card-hover-y));
            box-shadow: var(--shadow);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--card-color, var(--primary));
            background: rgba(var(--card-color-rgb, 111, 66, 193), 0.1);
            border-radius: 12px;
            flex-shrink: 0;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .stat-card.pending { --card-color: #ffc107; --card-color-rgb: 255, 193, 7; }
        .stat-card.processed { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .stat-card.total { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .stat-card.requests { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .stat-card.archived { --card-color: #6c757d; --card-color-rgb: 108, 117, 125; }
        .stat-card.quality { --card-color: #dc3545; --card-color-rgb: 220, 53, 69; }

        /* Section Heading */
        .section-heading {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 2.5rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-heading i {
            font-size: 1.25rem;
        }
        
        /* Legacy section title (keeping for compatibility) */
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 2.5rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Quick Actions */
        .quick-actions-section {
            margin-bottom: 2.5rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            text-decoration: none;
            color: var(--dark);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--border-light);
            position: relative;
        }

        .action-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--card-color, var(--primary));
            transition: width 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(var(--card-hover-y));
            box-shadow: var(--shadow);
        }

        .action-card:hover::before {
            width: 8px;
        }

        .action-icon {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(var(--card-color-rgb, 111, 66, 193), 0.1);
            color: var(--card-color, var(--primary));
            border-radius: 12px;
            flex-shrink: 0;
        }

        .action-content {
            flex-grow: 1;
        }

        .action-content h3 {
            margin: 0 0 0.35rem;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-content p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .action-arrow {
            color: var(--card-color, var(--primary));
            font-size: 1rem;
            opacity: 0.7;
            transition: transform 0.2s;
        }

        .action-card:hover .action-arrow {
            transform: translateX(4px);
            opacity: 1;
        }

        .action-card.purple { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }
        .action-card.blue { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .action-card.green { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .action-card.orange { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .action-card.teal { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
        .action-card.red { --card-color: #dc3545; --card-color-rgb: 220, 53, 69; }

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
            padding: 1.75rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            margin-bottom: 1.75rem;
            transition: var(--transition);
        }
        
        .card-section:hover {
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-header h3 i {
            font-size: 1rem;
            background: rgba(var(--card-color-rgb, 111, 66, 193), 0.1);
            color: var(--primary);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .view-more-btn {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            background: rgba(var(--card-color-rgb, 111, 66, 193), 0.08);
        }

        .view-more-btn:hover {
            color: var(--primary-dark);
            background: rgba(var(--card-color-rgb, 111, 66, 193), 0.12);
            text-decoration: none;
        }
        
        .view-more-btn i {
            font-size: 0.7rem;
        }

        /* Tables */
        .table-wrapper {
            max-height: 350px;
            overflow-y: auto;
            border-radius: var(--border-radius);
            scrollbar-width: thin;
        }
        
        .table-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background-color: var(--secondary-light);
            border-radius: 20px;
        }

        .records-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .records-table th,
        .records-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .records-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .records-table th:first-child {
            border-top-left-radius: var(--border-radius);
        }
        
        .records-table th:last-child {
            border-top-right-radius: var(--border-radius);
        }

        .records-table td {
            color: var(--secondary);
        }
        
        .records-table tr:hover td {
            background-color: rgba(111, 66, 193, 0.03);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 0.4rem;
        }

        .priority-urgent, .urgency-urgent {
            background: rgba(220, 53, 69, 0.15);
            color: #b82134;
        }
        
        .priority-urgent::before, .urgency-urgent::before {
            background: #dc3545;
        }

        .priority-high, .urgency-high {
            background: rgba(255, 193, 7, 0.15);
            color: #d6a206;
        }
        
        .priority-high::before, .urgency-high::before {
            background: #ffc107;
        }

        .priority-normal, .urgency-normal {
            background: rgba(23, 162, 184, 0.15);
            color: #138496;
        }
        
        .priority-normal::before, .urgency-normal::before {
            background: #17a2b8;
        }

        .alert-backup {
            background: rgba(40, 167, 69, 0.15);
            color: #218838;
        }
        
        .alert-backup::before {
            background: #28a745;
        }

        .alert-maintenance {
            background: rgba(23, 162, 184, 0.15);
            color: #138496;
        }
        
        .alert-maintenance::before {
            background: #17a2b8;
        }

        .alert-storage {
            background: rgba(255, 193, 7, 0.15);
            color: #d6a206;
        }
        
        .alert-storage::before {
            background: #ffc107;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.15);
            color: #b82134;
        }
        
        .alert-error::before {
            background: #dc3545;
        }

        /* Record & Activity Items */
        .record-item,
        .activity-item {
            padding: 1rem;
            background: white;
            margin-bottom: 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }
        
        .record-item::before,
        .activity-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
        }
        
        .record-item::before {
            background: var(--primary);
        }
        
        .activity-item::before {
            background: #28a745;
        }

        .record-item:hover,
        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.08);
            cursor: pointer;
        }

        .record-name,
        .activity-name {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .record-details,
        .activity-details {
            color: var(--secondary);
            font-size: 0.85rem;
            line-height: 1.5;
            padding-top: 0.25rem;
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
    include $root_path . '/includes/sidebar_records_officer.php';
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>Records Management Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="../records/new_patient_record.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> New Record
                </a>
                <a href="../records/record_search.php" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Search Records
                </a>
                <a href="../auth/employee_logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>        </section>
        
        <!-- Assignment Warning (if applicable) -->
        <?php if (!empty($assignment_warning)): ?>
        <section class="info-card" style="border-left-color: #ffc107; background: #fff3cd;">
            <h2 style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> Station Assignment Notice</h2>
            <p style="color: #856404;"><?php echo htmlspecialchars($assignment_warning); ?></p>
        </section>
        <?php endif; ?>
        
        <!-- System Overview Card --> <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-folder-open"></i> Records Management Overview</h2>
            <p>Welcome to your records management dashboard. Here you can access tools for patient record creation, data entry, archival, and quality control processes.</p>
        </section>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Records Overview
        </h2>
        <div class="stats-section">
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
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="quick-actions-section">
        <div class="action-grid">
            <a href="../records/new_patient_record.php" class="action-card purple">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-content">
                    <h3>New Patient Record</h3>
                    <p>Create new patient medical record file</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
            
            <a href="../records/record_search.php" class="action-card blue">
                <div class="action-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="action-content">
                    <h3>Search Records</h3>
                    <p>Find and retrieve patient medical records</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
            
            <a href="../records/data_entry.php" class="action-card green">
                <div class="action-icon">
                    <i class="fas fa-keyboard"></i>
                </div>
                <div class="action-content">
                    <h3>Data Entry</h3>
                    <p>Input and update patient information</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
            
            <a href="../records/record_archive.php" class="action-card orange">
                <div class="action-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <div class="action-content">
                    <h3>Archive Records</h3>
                    <p>Archive completed and old medical records</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
            
            <a href="../records/quality_control.php" class="action-card teal">
                <div class="action-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="action-content">
                    <h3>Quality Control</h3>
                    <p>Review and validate record accuracy</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
            
            <a href="../records/reports.php" class="action-card red">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-content">
                    <h3>Generate Reports</h3>
                    <p>Create statistical and compliance reports</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
        </div>
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
                            View All <i class="fas fa-chevron-right"></i>
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
                            View All <i class="fas fa-chevron-right"></i>
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
                            View All <i class="fas fa-chevron-right"></i>
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
                            View All <i class="fas fa-chevron-right"></i>
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
    </main>
</body>

</html>
