<?php
// dashboard_laboratory_tech.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not a laboratory technician, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'laboratory_tech') {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// -------------------- Data bootstrap (Laboratory Tech Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'pending_tests' => 0,
        'completed_today' => 0,
        'equipment_active' => 0,
        'samples_collected' => 0,
        'results_pending_review' => 0,
        'total_tests_month' => 0
    ],
    'pending_tests' => [],
    'recent_results' => [],
    'equipment_status' => [],
    'lab_alerts' => []
];

// Get lab tech info from employees table
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
    error_log("Laboratory tech dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Pending Tests
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE status = "pending" AND assigned_tech_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_tests'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Tests Completed Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE DATE(completed_date) = ? AND assigned_tech_id = ? AND status = "completed"');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['completed_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Active Equipment
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_equipment WHERE status = "active" AND assigned_tech_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['equipment_active'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Samples Collected Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_samples WHERE DATE(collection_date) = ? AND collected_by = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['samples_collected'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Results Pending Review
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE status = "results_ready" AND assigned_tech_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['results_pending_review'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Total Tests This Month
    $month_start = date('Y-m-01');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM lab_tests WHERE created_date >= ? AND assigned_tech_id = ?');
    $stmt->execute([$month_start, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['total_tests_month'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Pending Tests
try {
    $stmt = $pdo->prepare('
        SELECT lt.test_id, lt.test_type, lt.priority, lt.order_date, 
               p.first_name, p.last_name, p.patient_id, lt.specimen_type
        FROM lab_tests lt 
        JOIN patients p ON lt.patient_id = p.patient_id 
        WHERE lt.status = "pending" AND lt.assigned_tech_id = ? 
        ORDER BY lt.priority DESC, lt.order_date ASC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['pending_tests'][] = [
            'test_id' => $row['test_id'],
            'test_type' => $row['test_type'] ?? 'Lab Test',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'priority' => $row['priority'] ?? 'normal',
            'order_date' => date('M d, Y', strtotime($row['order_date'])),
            'specimen_type' => $row['specimen_type'] ?? 'Blood'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['pending_tests'] = [
        ['test_id' => '-', 'test_type' => 'No pending tests', 'patient_name' => '-', 'patient_id' => '-', 'priority' => 'normal', 'order_date' => '-', 'specimen_type' => '-']
    ];
}

// Recent Results
try {
    $stmt = $pdo->prepare('
        SELECT lt.test_id, lt.test_type, lt.completed_date, 
               p.first_name, p.last_name, p.patient_id, lt.status
        FROM lab_tests lt 
        JOIN patients p ON lt.patient_id = p.patient_id 
        WHERE lt.assigned_tech_id = ? AND lt.status IN ("completed", "results_ready") 
        ORDER BY lt.completed_date DESC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['recent_results'][] = [
            'test_id' => $row['test_id'],
            'test_type' => $row['test_type'] ?? 'Lab Test',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'completed_date' => date('M d, Y H:i', strtotime($row['completed_date'])),
            'status' => $row['status'] ?? 'completed'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['recent_results'] = [
        ['test_id' => '-', 'test_type' => 'No recent results', 'patient_name' => '-', 'patient_id' => '-', 'completed_date' => '-', 'status' => 'completed']
    ];
}

// Equipment Status
try {
    $stmt = $pdo->prepare('
        SELECT equipment_id, equipment_name, status, last_maintenance, next_maintenance
        FROM lab_equipment 
        WHERE assigned_tech_id = ? OR assigned_tech_id IS NULL
        ORDER BY status DESC, next_maintenance ASC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['equipment_status'][] = [
            'equipment_id' => $row['equipment_id'],
            'equipment_name' => $row['equipment_name'] ?? 'Lab Equipment',
            'status' => $row['status'] ?? 'active',
            'last_maintenance' => $row['last_maintenance'] ? date('M d, Y', strtotime($row['last_maintenance'])) : 'N/A',
            'next_maintenance' => $row['next_maintenance'] ? date('M d, Y', strtotime($row['next_maintenance'])) : 'N/A'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['equipment_status'] = [
        ['equipment_id' => 'EQ001', 'equipment_name' => 'Microscope', 'status' => 'active', 'last_maintenance' => 'Sep 01, 2025', 'next_maintenance' => 'Dec 01, 2025'],
        ['equipment_id' => 'EQ002', 'equipment_name' => 'Centrifuge', 'status' => 'active', 'last_maintenance' => 'Aug 15, 2025', 'next_maintenance' => 'Nov 15, 2025'],
        ['equipment_id' => 'EQ003', 'equipment_name' => 'Analyzer', 'status' => 'maintenance', 'last_maintenance' => 'Jul 20, 2025', 'next_maintenance' => 'Oct 20, 2025']
    ];
}

// Lab Alerts
try {
    $stmt = $pdo->prepare('
        SELECT la.alert_type, la.message, la.created_at, la.priority,
               p.first_name, p.last_name, p.patient_id
        FROM lab_alerts la 
        LEFT JOIN patients p ON la.patient_id = p.patient_id 
        WHERE la.tech_id = ? AND la.status = "active" 
        ORDER BY la.priority DESC, la.created_at DESC 
        LIMIT 4
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['lab_alerts'][] = [
            'alert_type' => $row['alert_type'] ?? 'general',
            'message' => $row['message'] ?? '',
            'patient_name' => $row['patient_id'] ? trim($row['first_name'] . ' ' . $row['last_name']) : 'System',
            'patient_id' => $row['patient_id'] ?? '-',
            'priority' => $row['priority'] ?? 'normal',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default alerts
    $defaults['lab_alerts'] = [
        ['alert_type' => 'info', 'message' => 'Equipment maintenance reminder: Centrifuge due for calibration', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'normal', 'date' => date('m/d/Y H:i')],
        ['alert_type' => 'warning', 'message' => 'Quality control test needed for Chemistry Analyzer', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'high', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Laboratory Tech Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #17a2b8;
            --primary-dark: #138496;
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
            background: linear-gradient(135deg, #e1f5fe 0%, #b3e5fc 100%);
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
        .stat-card.completed { --card-color: #28a745; }
        .stat-card.equipment { --card-color: #17a2b8; }
        .stat-card.samples { --card-color: #6f42c1; }
        .stat-card.results { --card-color: #fd7e14; }
        .stat-card.monthly { --card-color: #007bff; }

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

        .action-card.blue { --card-color: #007bff; }
        .action-card.teal { --card-color: #17a2b8; }
        .action-card.purple { --card-color: #6f42c1; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.green { --card-color: #28a745; }
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

        .lab-table {
            width: 100%;
            border-collapse: collapse;
        }

        .lab-table th,
        .lab-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .lab-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .lab-table td {
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

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-results_ready {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert-critical {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Test Item */
        .test-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .test-name {
            font-weight: 600;
            color: var(--dark);
        }

        .test-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Equipment Item */
        .equipment-item {
            padding: 0.75rem;
            border-left: 3px solid #17a2b8;
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .equipment-name {
            font-weight: 600;
            color: var(--dark);
        }

        .equipment-details {
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
    include '../../includes/sidebar_laboratory_tech.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                Laboratory Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Laboratory Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_tests']); ?></div>
                <div class="stat-label">Pending Tests</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['completed_today']); ?></div>
                <div class="stat-label">Completed Today</div>
            </div>
            <div class="stat-card equipment">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-cogs"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['equipment_active']); ?></div>
                <div class="stat-label">Active Equipment</div>
            </div>
            <div class="stat-card samples">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-vial"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['samples_collected']); ?></div>
                <div class="stat-label">Samples Collected</div>
            </div>
            <div class="stat-card results">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['results_pending_review']); ?></div>
                <div class="stat-label">Results Pending</div>
            </div>
            <div class="stat-card monthly">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_tests_month']); ?></div>
                <div class="stat-label">Tests This Month</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="../laboratory/sample_collection.php" class="action-card purple">
                <i class="fas fa-vial icon"></i>
                <h3>Collect Samples</h3>
                <p>Register and collect patient samples for testing</p>
            </a>
            <a href="../laboratory/test_processing.php" class="action-card teal">
                <i class="fas fa-flask icon"></i>
                <h3>Process Tests</h3>
                <p>Run laboratory tests and record results</p>
            </a>
            <a href="../laboratory/results_entry.php" class="action-card blue">
                <i class="fas fa-clipboard-list icon"></i>
                <h3>Enter Results</h3>
                <p>Input test results and generate reports</p>
            </a>
            <a href="../laboratory/quality_control.php" class="action-card orange">
                <i class="fas fa-shield-alt icon"></i>
                <h3>Quality Control</h3>
                <p>Perform quality control checks and validations</p>
            </a>
            <a href="../laboratory/equipment_maintenance.php" class="action-card green">
                <i class="fas fa-tools icon"></i>
                <h3>Equipment Maintenance</h3>
                <p>Maintain and calibrate laboratory equipment</p>
            </a>
            <a href="../laboratory/inventory.php" class="action-card red">
                <i class="fas fa-boxes icon"></i>
                <h3>Lab Inventory</h3>
                <p>Manage reagents, supplies, and consumables</p>
            </a>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Pending Tests -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-hourglass-half"></i> Pending Tests</h3>
                        <a href="../laboratory/pending_tests.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['pending_tests']) && $defaults['pending_tests'][0]['test_type'] !== 'No pending tests'): ?>
                        <div class="table-wrapper">
                            <table class="lab-table">
                                <thead>
                                    <tr>
                                        <th>Test</th>
                                        <th>Patient</th>
                                        <th>Priority</th>
                                        <th>Ordered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['pending_tests'] as $test): ?>
                                        <tr>
                                            <td>
                                                <div class="test-name"><?php echo htmlspecialchars($test['test_type']); ?></div>
                                                <small><?php echo htmlspecialchars($test['specimen_type']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($test['patient_name']); ?></div>
                                                <small><?php echo htmlspecialchars($test['patient_id']); ?></small>
                                            </td>
                                            <td><span class="status-badge priority-<?php echo $test['priority']; ?>"><?php echo ucfirst($test['priority']); ?></span></td>
                                            <td><?php echo htmlspecialchars($test['order_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending tests at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Results -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-clipboard-check"></i> Recent Results</h3>
                        <a href="../laboratory/test_results.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['recent_results']) && $defaults['recent_results'][0]['test_type'] !== 'No recent results'): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['recent_results'] as $result): ?>
                                <div class="test-item">
                                    <div class="test-name">
                                        <?php echo htmlspecialchars($result['test_type']); ?>
                                        <span class="status-badge status-<?php echo $result['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $result['status'])); ?></span>
                                    </div>
                                    <div class="test-details">
                                        Patient: <?php echo htmlspecialchars($result['patient_name']); ?> • 
                                        ID: <?php echo htmlspecialchars($result['patient_id']); ?> • 
                                        Completed: <?php echo htmlspecialchars($result['completed_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <p>No recent test results</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Equipment Status -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-cogs"></i> Equipment Status</h3>
                        <a href="../laboratory/equipment_status.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['equipment_status'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['equipment_status'] as $equipment): ?>
                                <div class="equipment-item">
                                    <div class="equipment-name">
                                        <?php echo htmlspecialchars($equipment['equipment_name']); ?>
                                        <span class="status-badge status-<?php echo $equipment['status']; ?>"><?php echo ucfirst($equipment['status']); ?></span>
                                    </div>
                                    <div class="equipment-details">
                                        ID: <?php echo htmlspecialchars($equipment['equipment_id']); ?> • 
                                        Last Maintenance: <?php echo htmlspecialchars($equipment['last_maintenance']); ?> • 
                                        Next Due: <?php echo htmlspecialchars($equipment['next_maintenance']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tools"></i>
                            <p>No equipment assigned</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Lab Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Lab Alerts</h3>
                        <a href="../laboratory/lab_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['lab_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['lab_alerts'] as $alert): ?>
                                <div class="test-item">
                                    <div class="test-name">
                                        <?php echo htmlspecialchars($alert['patient_name']); ?>
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </div>
                                    <div class="test-details">
                                        <?php echo htmlspecialchars($alert['message']); ?><br>
                                        <small><?php echo htmlspecialchars($alert['date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No lab alerts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</body>

</html>
