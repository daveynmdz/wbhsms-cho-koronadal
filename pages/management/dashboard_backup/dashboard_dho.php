<?php
// dashboard_dho.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not a DHO, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dho') {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../config/db.php';
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
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
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
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
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

        .stat-card.centers { --card-color: #007bff; }
        .stat-card.programs { --card-color: #28a745; }
        .stat-card.reports { --card-color: #ffc107; }
        .stat-card.compliance { --card-color: #17a2b8; }
        .stat-card.budget { --card-color: #fd7e14; }
        .stat-card.staff { --card-color: #6f42c1; }

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

        .stat-percentage {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stat-percentage::after {
            content: '%';
            font-size: 1.5rem;
            opacity: 0.7;
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

        .action-card.blue { --card-color: #007bff; }
        .action-card.green { --card-color: #28a745; }
        .action-card.yellow { --card-color: #ffc107; }
        .action-card.teal { --card-color: #17a2b8; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.purple { --card-color: #6f42c1; }

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

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
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

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-ongoing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-follow-up {
            background: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-normal {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-budget {
            background: #fff3cd;
            color: #856404;
        }

        .alert-compliance {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-program {
            background: #d4edda;
            color: #155724;
        }

        /* Progress Bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            transition: var(--transition);
        }

        /* Item Cards */
        .item-card {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .item-title {
            font-weight: 600;
            color: var(--dark);
        }

        .item-details {
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
    include '../../includes/sidebar_dho.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                District Health Officer Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            District Health Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card centers">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-hospital"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['health_centers']); ?></div>
                <div class="stat-label">Health Centers</div>
            </div>
            <div class="stat-card programs">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-project-diagram"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['active_programs']); ?></div>
                <div class="stat-label">Active Programs</div>
            </div>
            <div class="stat-card reports">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['monthly_reports']); ?></div>
                <div class="stat-label">Monthly Reports</div>
            </div>
            <div class="stat-card compliance">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                </div>
                <div class="stat-percentage"><?php echo number_format($defaults['stats']['compliance_rate']); ?></div>
                <div class="stat-label">Compliance Rate</div>
            </div>
            <div class="stat-card budget">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                </div>
                <div class="stat-percentage"><?php echo number_format($defaults['stats']['budget_utilization']); ?></div>
                <div class="stat-label">Budget Utilization</div>
            </div>
            <div class="stat-card staff">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['staff_count']); ?></div>
                <div class="stat-label">Staff Under Supervision</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="../dho/facility_management.php" class="action-card blue">
                <i class="fas fa-hospital icon"></i>
                <h3>Facility Management</h3>
                <p>Oversee health centers and clinic operations</p>
            </a>
            <a href="../dho/program_oversight.php" class="action-card green">
                <i class="fas fa-project-diagram icon"></i>
                <h3>Program Oversight</h3>
                <p>Monitor district health programs and initiatives</p>
            </a>
            <a href="../dho/compliance_monitoring.php" class="action-card yellow">
                <i class="fas fa-shield-alt icon"></i>
                <h3>Compliance Monitoring</h3>
                <p>Conduct facility inspections and assessments</p>
            </a>
            <a href="../dho/budget_management.php" class="action-card teal">
                <i class="fas fa-coins icon"></i>
                <h3>Budget Management</h3>
                <p>Track district health budget and expenditures</p>
            </a>
            <a href="../dho/staff_supervision.php" class="action-card orange">
                <i class="fas fa-users icon"></i>
                <h3>Staff Supervision</h3>
                <p>Manage district health personnel and performance</p>
            </a>
            <a href="../dho/reports.php" class="action-card purple">
                <i class="fas fa-chart-bar icon"></i>
                <h3>Generate Reports</h3>
                <p>Create district health reports and analytics</p>
            </a>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Health Centers Status -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-hospital"></i> Health Centers Status</h3>
                        <a href="../dho/all_centers.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['health_centers'])): ?>
                        <div class="table-wrapper">
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
                                                <?php echo htmlspecialchars($center['current_patients']); ?>/<?php echo htmlspecialchars($center['patient_capacity']); ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $center['patient_capacity'] > 0 ? round(($center['current_patients'] / $center['patient_capacity']) * 100) : 0; ?>%"></div>
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

                <!-- Program Reports -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-project-diagram"></i> Program Reports</h3>
                        <a href="../dho/program_reports.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['program_reports'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['program_reports'] as $program): ?>
                                <div class="item-card">
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($program['program_name']); ?>
                                        <span class="status-badge status-<?php echo $program['status']; ?>"><?php echo ucfirst($program['status']); ?></span>
                                    </div>
                                    <div class="item-details">
                                        Progress: <?php echo number_format($program['completion_percentage']); ?>% • 
                                        Beneficiaries: <?php echo number_format($program['actual_beneficiaries']); ?>/<?php echo number_format($program['target_beneficiaries']); ?> • 
                                        Budget: ₱<?php echo number_format($program['budget_spent']); ?>/₱<?php echo number_format($program['budget_allocated']); ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $program['completion_percentage']; ?>%"></div>
                                        </div>
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

            <!-- Right Column -->
            <div class="right-column">
                <!-- Compliance Monitoring -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-shield-alt"></i> Compliance Monitoring</h3>
                        <a href="../dho/compliance_reports.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['compliance_monitoring'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['compliance_monitoring'] as $assessment): ?>
                                <div class="item-card">
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($assessment['facility_name']); ?>
                                        <span class="status-badge status-<?php echo $assessment['status']; ?>"><?php echo ucfirst($assessment['status']); ?></span>
                                    </div>
                                    <div class="item-details">
                                        Type: <?php echo htmlspecialchars($assessment['assessment_type']); ?> • 
                                        Score: <?php echo number_format($assessment['compliance_score']); ?>% • 
                                        Date: <?php echo htmlspecialchars($assessment['assessment_date']); ?><br>
                                        <small><?php echo htmlspecialchars($assessment['findings']); ?></small>
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

                <!-- Priority Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Priority Alerts</h3>
                        <a href="../dho/priority_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['priority_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['priority_alerts'] as $alert): ?>
                                <div class="item-card">
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($alert['title']); ?>
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </div>
                                    <div class="item-details">
                                        <?php echo htmlspecialchars($alert['description']); ?><br>
                                        <small>Priority: <?php echo ucfirst($alert['priority']); ?> • <?php echo htmlspecialchars($alert['date']); ?></small>
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
    </section>
</body>

</html>
