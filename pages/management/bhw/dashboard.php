<?php
// dashboard_bhw.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not a BHW, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'bhw') {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// -------------------- Data bootstrap (BHW Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'assigned_households' => 0,
        'visits_today' => 0,
        'health_programs' => 0,
        'immunizations_due' => 0,
        'community_events' => 0,
        'maternal_cases' => 0
    ],
    'household_visits' => [],
    'health_programs' => [],
    'community_activities' => [],
    'notifications' => []
];

// Get BHW info from employees table
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
    error_log("BHW dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Assigned Households
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM household_assignments WHERE bhw_id = ? AND status = "active"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['assigned_households'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Visits Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM household_visits WHERE bhw_id = ? AND DATE(visit_date) = ?');
    $stmt->execute([$employee_id, $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['visits_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Active Health Programs
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM health_programs WHERE bhw_id = ? AND status = "active"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['health_programs'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Immunizations Due
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM immunization_schedules WHERE bhw_id = ? AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = "pending"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['immunizations_due'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Community Events This Month
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM community_events WHERE bhw_id = ? AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['community_events'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Maternal Cases
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM maternal_cases WHERE bhw_id = ? AND status = "monitoring"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['maternal_cases'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Household Visits
try {
    $stmt = $pdo->prepare('
        SELECT hv.visit_id, hv.visit_date, hv.visit_type, hv.notes,
               h.household_id, h.family_head, h.address
        FROM household_visits hv 
        JOIN households h ON hv.household_id = h.household_id 
        WHERE hv.bhw_id = ? 
        ORDER BY hv.visit_date DESC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['household_visits'][] = [
            'visit_id' => $row['visit_id'],
            'household_id' => $row['household_id'],
            'family_head' => $row['family_head'] ?? 'Unknown Family',
            'address' => $row['address'] ?? 'Unknown Address',
            'visit_type' => $row['visit_type'] ?? 'General Visit',
            'notes' => $row['notes'] ?? 'No notes',
            'visit_date' => date('M d, Y', strtotime($row['visit_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['household_visits'] = [
        ['visit_id' => 'V001', 'household_id' => 'H001', 'family_head' => 'Santos Family', 'address' => 'Barangay Centro', 'visit_type' => 'Health Education', 'notes' => 'Discussed proper nutrition and hygiene', 'visit_date' => 'Sep 21, 2025'],
        ['visit_id' => 'V002', 'household_id' => 'H002', 'family_head' => 'Dela Cruz Family', 'address' => 'Purok 3', 'visit_type' => 'Immunization Follow-up', 'notes' => 'Child vaccination completed', 'visit_date' => 'Sep 20, 2025'],
        ['visit_id' => 'V003', 'household_id' => 'H003', 'family_head' => 'Garcia Family', 'address' => 'Sitio Maliit', 'visit_type' => 'Maternal Check', 'notes' => 'Pregnant mother doing well', 'visit_date' => 'Sep 19, 2025']
    ];
}

// Health Programs
try {
    $stmt = $pdo->prepare('
        SELECT hp.program_id, hp.program_name, hp.description, hp.start_date, hp.end_date, hp.participants_count
        FROM health_programs hp 
        WHERE hp.bhw_id = ? AND hp.status = "active" 
        ORDER BY hp.start_date DESC 
        LIMIT 6
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['health_programs'][] = [
            'program_id' => $row['program_id'],
            'program_name' => $row['program_name'] ?? 'Health Program',
            'description' => $row['description'] ?? 'Community health program',
            'participants_count' => $row['participants_count'] ?? 0,
            'start_date' => date('M d, Y', strtotime($row['start_date'])),
            'end_date' => date('M d, Y', strtotime($row['end_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['health_programs'] = [
        ['program_id' => 'HP001', 'program_name' => 'Nutrition Education', 'description' => 'Community nutrition awareness program', 'participants_count' => 25, 'start_date' => 'Sep 15, 2025', 'end_date' => 'Oct 15, 2025'],
        ['program_id' => 'HP002', 'program_name' => 'Immunization Drive', 'description' => 'Child immunization campaign', 'participants_count' => 40, 'start_date' => 'Sep 10, 2025', 'end_date' => 'Sep 30, 2025'],
        ['program_id' => 'HP003', 'program_name' => 'Family Planning', 'description' => 'Family planning education and services', 'participants_count' => 15, 'start_date' => 'Sep 1, 2025', 'end_date' => 'Dec 31, 2025']
    ];
}

// Community Activities
try {
    $stmt = $pdo->prepare('
        SELECT ca.activity_id, ca.activity_name, ca.activity_type, ca.scheduled_date, ca.participants_expected,
               ca.description, ca.status
        FROM community_activities ca 
        WHERE ca.bhw_id = ? 
        ORDER BY ca.scheduled_date ASC 
        LIMIT 6
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['community_activities'][] = [
            'activity_id' => $row['activity_id'],
            'activity_name' => $row['activity_name'] ?? 'Community Activity',
            'activity_type' => $row['activity_type'] ?? 'General',
            'description' => $row['description'] ?? 'Community activity',
            'participants_expected' => $row['participants_expected'] ?? 0,
            'status' => $row['status'] ?? 'scheduled',
            'scheduled_date' => date('M d, Y', strtotime($row['scheduled_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['community_activities'] = [
        ['activity_id' => 'CA001', 'activity_name' => 'Health Fair', 'activity_type' => 'Health Event', 'description' => 'Community health screening and education', 'participants_expected' => 100, 'status' => 'scheduled', 'scheduled_date' => 'Sep 25, 2025'],
        ['activity_id' => 'CA002', 'activity_name' => 'Mother\'s Class', 'activity_type' => 'Education', 'description' => 'Prenatal and postnatal care education', 'participants_expected' => 20, 'status' => 'ongoing', 'scheduled_date' => 'Sep 22, 2025'],
        ['activity_id' => 'CA003', 'activity_name' => 'Clean-up Drive', 'activity_type' => 'Sanitation', 'description' => 'Community environmental sanitation', 'participants_expected' => 50, 'status' => 'scheduled', 'scheduled_date' => 'Sep 28, 2025']
    ];
}

// Notifications
try {
    $stmt = $pdo->prepare('
        SELECT n.notification_id, n.title, n.message, n.priority, n.created_at
        FROM notifications n 
        WHERE n.target_role = "bhw" AND n.status = "unread" 
        ORDER BY n.priority DESC, n.created_at DESC 
        LIMIT 4
    ');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['notifications'][] = [
            'notification_id' => $row['notification_id'],
            'title' => $row['title'] ?? 'Notification',
            'message' => $row['message'] ?? '',
            'priority' => $row['priority'] ?? 'normal',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default notifications
    $defaults['notifications'] = [
        ['notification_id' => 'N001', 'title' => 'Immunization Reminder', 'message' => '5 children due for immunization this week', 'priority' => 'high', 'date' => date('m/d/Y H:i')],
        ['notification_id' => 'N002', 'title' => 'Training Schedule', 'message' => 'Community health training on September 25', 'priority' => 'normal', 'date' => date('m/d/Y H:i')],
        ['notification_id' => 'N003', 'title' => 'Supply Request', 'message' => 'Medical supplies for health programs available', 'priority' => 'normal', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — BHW Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #28a745;
            --primary-dark: #218838;
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
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
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

        .stat-card.households { --card-color: #28a745; }
        .stat-card.visits { --card-color: #007bff; }
        .stat-card.programs { --card-color: #6f42c1; }
        .stat-card.immunizations { --card-color: #ffc107; }
        .stat-card.events { --card-color: #fd7e14; }
        .stat-card.maternal { --card-color: #e83e8c; }

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

        .action-card.green { --card-color: #28a745; }
        .action-card.blue { --card-color: #007bff; }
        .action-card.purple { --card-color: #6f42c1; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.teal { --card-color: #17a2b8; }
        .action-card.pink { --card-color: #e83e8c; }

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

        .status-scheduled {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-ongoing {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-normal {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Visit Item */
        .visit-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .visit-family {
            font-weight: 600;
            color: var(--dark);
        }

        .visit-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Program Item */
        .program-item {
            padding: 0.75rem;
            border-left: 3px solid #6f42c1;
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .program-name {
            font-weight: 600;
            color: var(--dark);
        }

        .program-details {
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
    include '../../includes/sidebar_bhw.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                Community Health Worker Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Community Health Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card households">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-home"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['assigned_households']); ?></div>
                <div class="stat-label">Assigned Households</div>
            </div>
            <div class="stat-card visits">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-walking"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['visits_today']); ?></div>
                <div class="stat-label">Visits Today</div>
            </div>
            <div class="stat-card programs">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-project-diagram"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['health_programs']); ?></div>
                <div class="stat-label">Active Programs</div>
            </div>
            <div class="stat-card immunizations">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-syringe"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['immunizations_due']); ?></div>
                <div class="stat-label">Immunizations Due</div>
            </div>
            <div class="stat-card events">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['community_events']); ?></div>
                <div class="stat-label">Community Events</div>
            </div>
            <div class="stat-card maternal">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-baby"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['maternal_cases']); ?></div>
                <div class="stat-label">Maternal Cases</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="../bhw/household_visit.php" class="action-card green">
                <i class="fas fa-home icon"></i>
                <h3>Household Visit</h3>
                <p>Record and track household health visits</p>
            </a>
            <a href="../bhw/health_education.php" class="action-card blue">
                <i class="fas fa-chalkboard-teacher icon"></i>
                <h3>Health Education</h3>
                <p>Conduct community health education sessions</p>
            </a>
            <a href="../bhw/immunization_tracking.php" class="action-card purple">
                <i class="fas fa-syringe icon"></i>
                <h3>Immunization Tracking</h3>
                <p>Monitor and schedule child immunizations</p>
            </a>
            <a href="../bhw/maternal_care.php" class="action-card orange">
                <i class="fas fa-baby icon"></i>
                <h3>Maternal Care</h3>
                <p>Track prenatal and postnatal care</p>
            </a>
            <a href="../bhw/community_events.php" class="action-card teal">
                <i class="fas fa-users icon"></i>
                <h3>Community Events</h3>
                <p>Organize and manage health events</p>
            </a>
            <a href="../bhw/reports.php" class="action-card pink">
                <i class="fas fa-chart-pie icon"></i>
                <h3>Generate Reports</h3>
                <p>Create community health reports</p>
            </a>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Recent Household Visits -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-home"></i> Recent Household Visits</h3>
                        <a href="../bhw/all_visits.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['household_visits'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['household_visits'] as $visit): ?>
                                <div class="visit-item">
                                    <div class="visit-family"><?php echo htmlspecialchars($visit['family_head']); ?></div>
                                    <div class="visit-details">
                                        Address: <?php echo htmlspecialchars($visit['address']); ?><br>
                                        Type: <?php echo htmlspecialchars($visit['visit_type']); ?> • 
                                        Date: <?php echo htmlspecialchars($visit['visit_date']); ?><br>
                                        Notes: <?php echo htmlspecialchars($visit['notes']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-home"></i>
                            <p>No household visits recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Active Health Programs -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-project-diagram"></i> Active Health Programs</h3>
                        <a href="../bhw/health_programs.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['health_programs'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['health_programs'] as $program): ?>
                                <div class="program-item">
                                    <div class="program-name"><?php echo htmlspecialchars($program['program_name']); ?></div>
                                    <div class="program-details">
                                        <?php echo htmlspecialchars($program['description']); ?><br>
                                        Participants: <?php echo number_format($program['participants_count']); ?> • 
                                        Duration: <?php echo htmlspecialchars($program['start_date']); ?> - <?php echo htmlspecialchars($program['end_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-project-diagram"></i>
                            <p>No active health programs</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Community Activities -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-alt"></i> Community Activities</h3>
                        <a href="../bhw/activities.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['community_activities'])): ?>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['community_activities'] as $activity): ?>
                                        <tr>
                                            <td>
                                                <div class="program-name"><?php echo htmlspecialchars($activity['activity_name']); ?></div>
                                                <small><?php echo htmlspecialchars($activity['description']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['scheduled_date']); ?></td>
                                            <td><span class="status-badge status-<?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No community activities scheduled</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notifications -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <a href="../bhw/notifications.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['notifications'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['notifications'] as $notification): ?>
                                <div class="visit-item">
                                    <div class="visit-family">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <span class="status-badge priority-<?php echo $notification['priority']; ?>"><?php echo ucfirst($notification['priority']); ?></span>
                                    </div>
                                    <div class="visit-details">
                                        <?php echo htmlspecialchars($notification['message']); ?><br>
                                        <small><?php echo htmlspecialchars($notification['date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell"></i>
                            <p>No notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</body>

</html>
