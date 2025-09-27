<?php
// dashboard_dho.php
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
    error_log('DHO Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dho') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to DHO dashboard - User: ' . $_SESSION['employee_id'] . ' with role: ' . 
              ($_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}

// Log session data for debugging
error_log('DHO Dashboard - Session Data: ' . print_r($_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once $root_path . '/config/db.php';

// Debug connection status
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// -------------------- Data bootstrap (DHO Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'health_centers' => 0,
        'active_programs' => 0,
        'monthly_reports' => 0,
        'compliance_rate' => 0,
        'budget_utilization' => 0,
        'staff_count' => 0
    ],
    'health_centers' => [],
    'program_reports' => [],
    'compliance_monitoring' => [],
    'priority_alerts' => []
];

// Get DHO info from employees table
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
    error_log("DHO dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Health Centers under jurisdiction
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM health_centers WHERE dho_id = ? AND status = "active"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['health_centers'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Active Health Programs
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM district_programs WHERE dho_id = ? AND status = "active"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['active_programs'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Monthly Reports This Month
    $current_month = date('Y-m');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM monthly_reports WHERE dho_id = ? AND DATE_FORMAT(report_date, "%Y-%m") = ?');
    $stmt->execute([$employee_id, $current_month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['monthly_reports'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Compliance Rate (percentage)
    $stmt = $pdo->prepare('SELECT AVG(compliance_score) as avg_compliance FROM compliance_assessments WHERE dho_id = ? AND assessment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['compliance_rate'] = round($row['avg_compliance'] ?? 0);
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Budget Utilization (percentage)
    $stmt = $pdo->prepare('SELECT (SUM(amount_spent) / SUM(budget_allocated)) * 100 as utilization_rate FROM budget_tracking WHERE dho_id = ? AND fiscal_year = YEAR(CURDATE())');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['budget_utilization'] = round($row['utilization_rate'] ?? 0);
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Total Staff under supervision
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM employees WHERE supervisor_id = ? AND status = "active"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['staff_count'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Health Centers Status
try {
    $stmt = $pdo->prepare('
        SELECT hc.center_id, hc.center_name, hc.location, hc.status, hc.last_inspection,
               hc.patient_capacity, hc.current_patients, hc.staff_count
        FROM health_centers hc 
        WHERE hc.dho_id = ? 
        ORDER BY hc.last_inspection DESC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['health_centers'][] = [
            'center_id' => $row['center_id'],
            'center_name' => $row['center_name'] ?? 'Health Center',
            'location' => $row['location'] ?? 'Unknown Location',
            'status' => $row['status'] ?? 'active',
            'last_inspection' => $row['last_inspection'] ? date('M d, Y', strtotime($row['last_inspection'])) : 'Not inspected',
            'patient_capacity' => $row['patient_capacity'] ?? 0,
            'current_patients' => $row['current_patients'] ?? 0,
            'staff_count' => $row['staff_count'] ?? 0
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['health_centers'] = [
        ['center_id' => 'HC001', 'center_name' => 'Central Health Center', 'location' => 'Barangay Centro', 'status' => 'active', 'last_inspection' => 'Sep 15, 2025', 'patient_capacity' => 100, 'current_patients' => 75, 'staff_count' => 12],
        ['center_id' => 'HC002', 'center_name' => 'Rural Health Unit 1', 'location' => 'Barangay San Jose', 'status' => 'active', 'last_inspection' => 'Sep 10, 2025', 'patient_capacity' => 50, 'current_patients' => 35, 'staff_count' => 8],
        ['center_id' => 'HC003', 'center_name' => 'Community Health Center', 'location' => 'Barangay Poblacion', 'status' => 'maintenance', 'last_inspection' => 'Sep 5, 2025', 'patient_capacity' => 75, 'current_patients' => 20, 'staff_count' => 6]
    ];
}

// Program Reports
try {
    $stmt = $pdo->prepare('
        SELECT pr.program_id, pr.program_name, pr.status, pr.budget_allocated, pr.budget_spent,
               pr.target_beneficiaries, pr.actual_beneficiaries, pr.completion_percentage
        FROM district_programs pr 
        WHERE pr.dho_id = ? 
        ORDER BY pr.completion_percentage ASC 
        LIMIT 6
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['program_reports'][] = [
            'program_id' => $row['program_id'],
            'program_name' => $row['program_name'] ?? 'Health Program',
            'status' => $row['status'] ?? 'active',
            'budget_allocated' => $row['budget_allocated'] ?? 0,
            'budget_spent' => $row['budget_spent'] ?? 0,
            'target_beneficiaries' => $row['target_beneficiaries'] ?? 0,
            'actual_beneficiaries' => $row['actual_beneficiaries'] ?? 0,
            'completion_percentage' => $row['completion_percentage'] ?? 0
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['program_reports'] = [
        ['program_id' => 'DP001', 'program_name' => 'Immunization Campaign', 'status' => 'active', 'budget_allocated' => 500000, 'budget_spent' => 350000, 'target_beneficiaries' => 1000, 'actual_beneficiaries' => 750, 'completion_percentage' => 75],
        ['program_id' => 'DP002', 'program_name' => 'Maternal Health Program', 'status' => 'active', 'budget_allocated' => 750000, 'budget_spent' => 600000, 'target_beneficiaries' => 500, 'actual_beneficiaries' => 450, 'completion_percentage' => 90],
        ['program_id' => 'DP003', 'program_name' => 'TB Control Program', 'status' => 'ongoing', 'budget_allocated' => 300000, 'budget_spent' => 150000, 'target_beneficiaries' => 200, 'actual_beneficiaries' => 120, 'completion_percentage' => 60]
    ];
}

// Compliance Monitoring
try {
    $stmt = $pdo->prepare('
        SELECT cm.assessment_id, cm.facility_name, cm.assessment_type, cm.compliance_score,
               cm.assessment_date, cm.findings, cm.status
        FROM compliance_assessments cm 
        WHERE cm.dho_id = ? 
        ORDER BY cm.assessment_date DESC 
        LIMIT 6
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['compliance_monitoring'][] = [
            'assessment_id' => $row['assessment_id'],
            'facility_name' => $row['facility_name'] ?? 'Health Facility',
            'assessment_type' => $row['assessment_type'] ?? 'General',
            'compliance_score' => $row['compliance_score'] ?? 0,
            'findings' => $row['findings'] ?? 'Assessment completed',
            'status' => $row['status'] ?? 'completed',
            'assessment_date' => date('M d, Y', strtotime($row['assessment_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['compliance_monitoring'] = [
        ['assessment_id' => 'CA001', 'facility_name' => 'Central Health Center', 'assessment_type' => 'Quality Assurance', 'compliance_score' => 92, 'findings' => 'Excellent compliance with safety protocols', 'status' => 'completed', 'assessment_date' => 'Sep 20, 2025'],
        ['assessment_id' => 'CA002', 'facility_name' => 'Rural Health Unit 1', 'assessment_type' => 'Safety Inspection', 'compliance_score' => 88, 'findings' => 'Minor improvements needed in record keeping', 'status' => 'completed', 'assessment_date' => 'Sep 18, 2025'],
        ['assessment_id' => 'CA003', 'facility_name' => 'Community Health Center', 'assessment_type' => 'Standard Review', 'compliance_score' => 75, 'findings' => 'Equipment maintenance required', 'status' => 'follow-up', 'assessment_date' => 'Sep 15, 2025']
    ];
}

// Priority Alerts
try {
    $stmt = $pdo->prepare('
        SELECT pa.alert_id, pa.alert_type, pa.title, pa.description, pa.priority, pa.created_at, pa.status
        FROM priority_alerts pa 
        WHERE pa.target_role = "dho" AND pa.status = "active" 
        ORDER BY pa.priority DESC, pa.created_at DESC 
        LIMIT 4
    ');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['priority_alerts'][] = [
            'alert_id' => $row['alert_id'],
            'alert_type' => $row['alert_type'] ?? 'general',
            'title' => $row['title'] ?? 'Alert',
            'description' => $row['description'] ?? '',
            'priority' => $row['priority'] ?? 'normal',
            'status' => $row['status'] ?? 'active',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default alerts
    $defaults['priority_alerts'] = [
        ['alert_id' => 'PA001', 'alert_type' => 'budget', 'title' => 'Budget Review Required', 'description' => 'Q3 budget utilization requires district office review', 'priority' => 'high', 'status' => 'active', 'date' => date('m/d/Y H:i')],
        ['alert_id' => 'PA002', 'alert_type' => 'compliance', 'title' => 'Facility Inspection Due', 'description' => '3 health centers pending monthly compliance inspection', 'priority' => 'normal', 'status' => 'active', 'date' => date('m/d/Y H:i')],
        ['alert_id' => 'PA003', 'alert_type' => 'program', 'title' => 'Program Milestone', 'description' => 'Immunization program reached 75% completion target', 'priority' => 'normal', 'status' => 'active', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — DHO Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Use absolute paths for all styles -->
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/sidebar.css">
    <style>
        :root {
            --primary: #007bff;
            --primary-light: #3e97ff;
            --primary-dark: #0056b3;
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
            background: linear-gradient(135deg, #e9f5ff 0%, #d0e7ff 100%);
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
            box-shadow: var(--shadow);
        }

        .info-card::before,
        .info-card::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }

        .info-card::before {
            width: 300px;
            height: 300px;
            right: -100px;
            top: -100px;
        }

        .info-card::after {
            width: 200px;
            height: 200px;
            left: -50px;
            bottom: -50px;
        }

        .info-card h2 {
            position: relative;
            z-index: 1;
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-card p {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 700px;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .welcome-message h1 {
                font-size: 1.8rem;
            }
            
            .dashboard-actions {
                margin-top: 1rem;
                flex-wrap: wrap;
            }
        }

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

        /* Stats Section */
        .stats-section {
            margin-bottom: 2rem;
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
            align-items: center;
            gap: 1rem;
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
            height: 100%;
            width: 4px;
            background: var(--card-color, var(--primary));
        }

        .stat-card:hover {
            transform: translateY(var(--card-hover-y));
            box-shadow: var(--shadow);
        }

        .stat-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--card-color, var(--primary));
            background: rgba(var(--card-color-rgb, 0, 123, 255), 0.1);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stat-details {
            flex-grow: 1;
        }

        .stat-number {
            font-size: 1.75rem;
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

        .stat-card.centers { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .stat-card.programs { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .stat-card.reports { --card-color: #ffc107; --card-color-rgb: 255, 193, 7; }
        .stat-card.compliance { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
        .stat-card.budget { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .stat-card.staff { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }

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
            background: rgba(var(--card-color-rgb, 0, 123, 255), 0.1);
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

        .action-card.blue { --card-color: #007bff; --card-color-rgb: 0, 123, 255; }
        .action-card.green { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .action-card.yellow { --card-color: #ffc107; --card-color-rgb: 255, 193, 7; }
        .action-card.teal { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
        .action-card.orange { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .action-card.purple { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }

        /* Dashboard Content */
        .dashboard-content {
            margin-top: 2.5rem;
        }

        .content-columns {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1200px) {
            .content-columns {
                grid-template-columns: 1fr;
            }
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .content-card:hover {
            box-shadow: var(--shadow);
        }

        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .content-card-header h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-action-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            transition: var(--transition);
        }

        .card-action-link:hover {
            color: var(--primary-dark);
        }

        .card-action-link i {
            font-size: 0.75rem;
            transition: transform 0.2s;
        }

        .card-action-link:hover i {
            transform: translateX(3px);
        }

        .content-card-body {
            padding: 1.5rem;
        }

        /* Data List */
        .data-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .list-item {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 1rem;
            transition: var(--transition);
        }

        .list-item:hover {
            background: var(--light-hover);
            transform: translateX(4px);
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .item-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.05rem;
        }

        .item-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Program Items */
        .item-progress {
            margin: 0.5rem 0;
        }

        .progress-text {
            display: block;
            font-size: 0.9rem;
            color: var(--dark);
            margin-bottom: 0.35rem;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            transition: var(--transition);
        }

        /* Compliance Items */
        .item-score {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin: 0.5rem 0;
        }

        .score-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(
                var(--primary) calc(var(--score) * 3.6deg), 
                #e9ecef 0deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
        }

        .score-circle::before {
            content: '';
            position: absolute;
            width: 80%;
            height: 80%;
            background: white;
            border-radius: 50%;
        }

        .score-circle span {
            position: relative;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
        }

        .score-details {
            font-size: 0.9rem;
            color: var(--secondary);
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .item-findings {
            font-size: 0.9rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border-light);
            color: var(--dark);
        }

        /* Alert Items */
        .alert-message {
            margin: 0.5rem 0;
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--dark);
        }

        .priority-label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .priority-high {
            background: rgba(220, 53, 69, 0.15);
            color: #721c24;
        }

        .priority-normal {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
        }

        .priority-low {
            background: rgba(40, 167, 69, 0.15);
            color: #155724;
        }

        /* Capacity Display */
        .capacity-display {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .capacity-text {
            font-size: 0.9rem;
            color: var(--dark);
        }

        /* Tables */
        .responsive-table {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
        }

        .data-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr {
            border-bottom: 1px solid var(--border-light);
        }

        .data-table tr:last-child {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: var(--light);
        }

        /* Status Badges */
        .status-active {
            background: rgba(40, 167, 69, 0.15);
            color: #155724;
        }

        .status-maintenance {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
        }

        .status-inactive {
            background: rgba(220, 53, 69, 0.15);
            color: #721c24;
        }

        .status-ongoing {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.15);
            color: #155724;
        }

        .status-follow-up {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
        }

        .alert-budget {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
        }

        .alert-compliance {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
        }

        .alert-program {
            background: rgba(40, 167, 69, 0.15);
            color: #155724;
        }

        /* Item Details */
        .item-details {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.5rem;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .item-details span {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Empty States */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--secondary);
            text-align: center;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.6;
            color: var(--secondary-light);
        }

        .empty-state p {
            margin: 0;
            font-size: 1rem;
        }

        /* Animations */
        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fade-right {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes fade-left {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .animated {
            animation-duration: 0.6s;
            animation-fill-mode: both;
        }

        .fade-in { animation-name: fade-in; }
        .fade-up { animation-name: fade-up; }
        .fade-right { animation-name: fade-right; }
        .fade-left { animation-name: fade-left; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }

            .item-details {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .item-score {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .score-circle {
                margin: 0 auto 1rem;
            }
        }
    </style>
</head>

<body>

    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include $root_path . '/includes/sidebar_dho.php';
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>District Health Officer Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="facility_management.php" class="btn btn-primary">
                    <i class="fas fa-hospital"></i> Manage Facilities
                </a>
                <a href="compliance_monitoring.php" class="btn btn-secondary">
                    <i class="fas fa-shield-alt"></i> Check Compliance
                </a>
                <a href="../auth/employee_logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </section>
        
        <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-chart-line"></i> District Health Overview</h2>
            <p>Welcome to your district health management dashboard. Monitor health centers, program performance, compliance rates, and resource allocation across the district.</p>
        </section>

        <!-- Statistics Cards -->
        <section class="stats-section">
            <h2 class="section-heading"><i class="fas fa-chart-pie"></i> Key Metrics</h2>
            
            <div class="stats-grid">
                <div class="stat-card centers animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="stat-icon"><i class="fas fa-hospital"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['health_centers']); ?></div>
                        <div class="stat-label">Health Centers</div>
                    </div>
                </div>
                
                <div class="stat-card programs animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="stat-icon"><i class="fas fa-project-diagram"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['active_programs']); ?></div>
                        <div class="stat-label">Active Programs</div>
                    </div>
                </div>
                
                <div class="stat-card reports animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['monthly_reports']); ?></div>
                        <div class="stat-label">Monthly Reports</div>
                    </div>
                </div>
                
                <div class="stat-card compliance animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['compliance_rate']); ?>%</div>
                        <div class="stat-label">Compliance Rate</div>
                    </div>
                </div>
                
                <div class="stat-card budget animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['budget_utilization']); ?>%</div>
                        <div class="stat-label">Budget Utilization</div>
                    </div>
                </div>
                
                <div class="stat-card staff animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['staff_count']); ?></div>
                        <div class="stat-label">Staff Under Supervision</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="quick-actions-section">
            <h2 class="section-heading"><i class="fas fa-bolt"></i> Quick Actions</h2>
            
            <div class="action-grid">
                <a href="facility_management.php" class="action-card blue animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="action-icon"><i class="fas fa-hospital"></i></div>
                    <div class="action-content">
                        <h3>Facility Management</h3>
                        <p>Oversee health centers and clinic operations</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="program_oversight.php" class="action-card green animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="action-icon"><i class="fas fa-project-diagram"></i></div>
                    <div class="action-content">
                        <h3>Program Oversight</h3>
                        <p>Monitor health programs and initiatives</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="compliance_monitoring.php" class="action-card yellow animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="action-content">
                        <h3>Compliance Monitoring</h3>
                        <p>Conduct facility inspections and assessments</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="budget_management.php" class="action-card teal animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="action-icon"><i class="fas fa-coins"></i></div>
                    <div class="action-content">
                        <h3>Budget Management</h3>
                        <p>Track district health budget and expenditures</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="staff_supervision.php" class="action-card orange animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="action-icon"><i class="fas fa-users"></i></div>
                    <div class="action-content">
                        <h3>Staff Supervision</h3>
                        <p>Manage personnel and performance</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="reports.php" class="action-card purple animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="action-content">
                        <h3>Generate Reports</h3>
                        <p>Create district health reports and analytics</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
            </div>
        </section>

        <!-- Dashboard Data Layout -->
        <section class="dashboard-content">
            <div class="content-columns">
                <!-- Left Column -->
                <div class="content-column main-column animate-on-scroll" data-animation="fade-right" data-delay="100">
                    <!-- Health Centers Status -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-hospital"></i> Health Centers Status</h3>
                            <a href="all_centers.php" class="card-action-link">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['health_centers'])): ?>
                                <div class="responsive-table">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Center</th>
                                                <th>Capacity</th>
                                                <th>Staff</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($defaults['health_centers'] as $center): ?>
                                                <tr>
                                                    <td>
                                                        <div class="item-title"><?php echo htmlspecialchars($center['center_name']); ?></div>
                                                        <small><?php echo htmlspecialchars($center['location']); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="capacity-display">
                                                            <span class="capacity-text"><?php echo htmlspecialchars($center['current_patients']); ?>/<?php echo htmlspecialchars($center['patient_capacity']); ?></span>
                                                            <div class="progress-bar">
                                                                <div class="progress-fill" style="width: <?php echo $center['patient_capacity'] > 0 ? round(($center['current_patients'] / $center['patient_capacity']) * 100) : 0; ?>%"></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo number_format($center['staff_count']); ?></td>
                                                    <td><span class="status-badge status-<?php echo $center['status']; ?>"><?php echo ucfirst($center['status']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-hospital"></i>
                                    <p>No health centers assigned</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Program Reports -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-project-diagram"></i> Program Reports</h3>
                            <a href="program_reports.php" class="card-action-link">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['program_reports'])): ?>
                                <div class="data-list">
                                    <?php foreach ($defaults['program_reports'] as $program): ?>
                                        <div class="list-item program-item">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <?php echo htmlspecialchars($program['program_name']); ?>
                                                </div>
                                                <div class="item-badge status-<?php echo $program['status']; ?>">
                                                    <?php echo ucfirst($program['status']); ?>
                                                </div>
                                            </div>
                                            <div class="item-progress">
                                                <span class="progress-text"><?php echo number_format($program['completion_percentage']); ?>% Complete</span>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $program['completion_percentage']; ?>%"></div>
                                                </div>
                                            </div>
                                            <div class="item-details">
                                                <span><i class="fas fa-users"></i> <?php echo number_format($program['actual_beneficiaries']); ?>/<?php echo number_format($program['target_beneficiaries']); ?> beneficiaries</span>
                                                <span><i class="fas fa-coins"></i> ₱<?php echo number_format($program['budget_spent']); ?>/₱<?php echo number_format($program['budget_allocated']); ?> budget</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-project-diagram"></i>
                                    <p>No active programs</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="content-column side-column animate-on-scroll" data-animation="fade-left" data-delay="200">
                    <!-- Compliance Monitoring -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-shield-alt"></i> Compliance Monitoring</h3>
                            <a href="compliance_reports.php" class="card-action-link">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['compliance_monitoring'])): ?>
                                <div class="data-list">
                                    <?php foreach ($defaults['compliance_monitoring'] as $assessment): ?>
                                        <div class="list-item compliance-item">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <?php echo htmlspecialchars($assessment['facility_name']); ?>
                                                </div>
                                                <div class="item-badge status-<?php echo $assessment['status']; ?>">
                                                    <?php echo ucfirst($assessment['status']); ?>
                                                </div>
                                            </div>
                                            <div class="item-score">
                                                <div class="score-circle" style="--score: <?php echo $assessment['compliance_score']; ?>%">
                                                    <span><?php echo number_format($assessment['compliance_score']); ?>%</span>
                                                </div>
                                                <div class="score-details">
                                                    <div><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars($assessment['assessment_type']); ?></div>
                                                    <div><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($assessment['assessment_date']); ?></div>
                                                </div>
                                            </div>
                                            <div class="item-findings">
                                                <?php echo htmlspecialchars($assessment['findings']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-shield-alt"></i>
                                    <p>No compliance assessments</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Priority Alerts -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Priority Alerts</h3>
                            <a href="priority_alerts.php" class="card-action-link">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['priority_alerts'])): ?>
                                <div class="data-list">
                                    <?php foreach ($defaults['priority_alerts'] as $alert): ?>
                                        <div class="list-item alert-item">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <?php echo htmlspecialchars($alert['title']); ?>
                                                </div>
                                                <div class="item-badge alert-<?php echo $alert['alert_type']; ?>">
                                                    <?php echo ucfirst($alert['alert_type']); ?>
                                                </div>
                                            </div>
                                            <div class="alert-message">
                                                <?php echo htmlspecialchars($alert['description']); ?>
                                            </div>
                                            <div class="item-details">
                                                <span class="priority-label priority-<?php echo $alert['priority']; ?>">
                                                    <i class="fas fa-flag"></i> <?php echo ucfirst($alert['priority']); ?> Priority
                                                </span>
                                                <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($alert['date']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <p>No priority alerts</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Animation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate elements when they come into view
            const animateElements = document.querySelectorAll('.animate-on-scroll');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const animation = element.dataset.animation || 'fade-in';
                        const delay = element.dataset.delay || 0;
                        
                        setTimeout(() => {
                            element.classList.add('animated', animation);
                            element.style.visibility = 'visible';
                        }, delay);
                        
                        observer.unobserve(element);
                    }
                });
            }, { threshold: 0.1 });
            
            animateElements.forEach(element => {
                element.style.visibility = 'hidden';
                observer.observe(element);
            });
        });
    </script>
</body>

</html>
