<?php
// dashboard_doctor.php
// Using the same approach as admin dashboard for consistency
// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Authentication check - refactored to eliminate redirect loops
// Check 1: Is the user logged in at all?
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    // User is not logged in - redirect to login, but prevent redirect loops
    error_log('Doctor Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
// Make sure role comparison is case-insensitive
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'doctor') {
    // User has wrong role - log and redirect
    error_log('Access denied: User ' . $_SESSION['employee_id'] . ' with role ' . 
              ($_SESSION['role'] ?? 'none') . ' attempted to access doctor dashboard');
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}


// Log session data for debugging
error_log('Doctor Dashboard - Session Data: ' . print_r($_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/staff_assignment.php';

// Debug connection status - should log both connections
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

// Set variables
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Check staff assignment for today (non-blocking)
$assignment = null;
$assignment_warning = '';
try {
    $assignment = getStaffAssignment($employee_id);
    if (!$assignment) {
        // Not assigned today, but allow access with warning
        error_log('Doctor Dashboard: No active staff assignment for today - allowing access with warning.');
        $assignment_warning = 'You are not assigned to any station today. Some queue management features may be limited. Please contact the administrator if you need station access.';
    }
} catch (Exception $e) {
    // Staff assignment function failed, log error but continue
    error_log('Doctor Dashboard: Staff assignment check failed: ' . $e->getMessage());
    $assignment_warning = 'Unable to verify station assignment. Some features may be limited.';
}

// -------------------- Data bootstrap (Doctor Dashboard) --------------------
$defaults = [
    'name' => trim(($_SESSION['employee_first_name'] ?? 'Unknown') . ' ' . ($_SESSION['employee_last_name'] ?? 'User')),
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'today_appointments' => 0,
        'pending_consultations' => 0,
        'patients_seen_today' => 0,
        'pending_prescriptions' => 0,
        'total_patients' => 0,
        'follow_ups_due' => 0
    ],
    'todays_schedule' => [],
    'pending_consultations' => [],
    'recent_patients' => [],
    'medical_alerts' => []
];

// Get doctor info from employees table
try {
    if ($pdo) {
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
    }
} catch (PDOException $e) {
    error_log("Doctor dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    if ($pdo) {
        // Today's Appointments
        $today = date('Y-m-d');
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = ? AND doctor_id = ?');
        $stmt->execute([$today, $employee_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $defaults['stats']['today_appointments'] = $row['count'] ?? 0;
    }
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    if ($pdo) {
        // Pending Consultations
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM appointments WHERE status = "scheduled" AND doctor_id = ?');
        $stmt->execute([$employee_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $defaults['stats']['pending_consultations'] = $row['count'] ?? 0;
    }
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Patients Seen Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE DATE(appointment_date) = ? AND doctor_id = ? AND status = "completed"');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['patients_seen_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Pending Prescriptions
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = "pending"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_prescriptions'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Total Patients under care
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['total_patients'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Follow-ups Due
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND follow_up_date <= ? AND status = "follow_up_scheduled"');
    $stmt->execute([$employee_id, date('Y-m-d')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['follow_ups_due'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Today's Schedule (next 5 appointments)
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        SELECT a.appointment_time, a.appointment_type, a.status, 
               p.first_name, p.last_name, p.patient_id, a.chief_complaint
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.patient_id 
        WHERE DATE(a.appointment_date) = ? AND a.doctor_id = ? 
        ORDER BY a.appointment_time ASC 
        LIMIT 5
    ');
    $stmt->execute([$today, $employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['todays_schedule'][] = [
            'time' => date('H:i', strtotime($row['appointment_time'])),
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'type' => $row['appointment_type'] ?? 'Consultation',
            'status' => $row['status'] ?? 'scheduled',
            'chief_complaint' => $row['chief_complaint'] ?? '-'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default schedule
    $defaults['todays_schedule'] = [
        ['time' => '08:00', 'patient_name' => 'No appointments', 'patient_id' => '-', 'type' => '-', 'status' => 'scheduled', 'chief_complaint' => '-']
    ];
}

// Recent Patients (latest 5)
try {
    $stmt = $pdo->prepare('
        SELECT DISTINCT p.first_name, p.last_name, p.patient_id, 
               a.appointment_date, a.diagnosis, a.status
        FROM patients p 
        JOIN appointments a ON p.patient_id = a.patient_id 
        WHERE a.doctor_id = ? 
        ORDER BY a.appointment_date DESC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['recent_patients'][] = [
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'last_visit' => date('M d, Y', strtotime($row['appointment_date'])),
            'diagnosis' => $row['diagnosis'] ?? 'Consultation',
            'status' => $row['status'] ?? 'completed'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['recent_patients'] = [
        ['patient_name' => 'No recent patients', 'patient_id' => '-', 'last_visit' => '-', 'diagnosis' => '-', 'status' => '-']
    ];
}

// Medical Alerts
try {
    $stmt = $pdo->prepare('
        SELECT p.first_name, p.last_name, p.patient_id, ma.alert_type, ma.message, ma.created_at
        FROM medical_alerts ma 
        JOIN patients p ON ma.patient_id = p.patient_id 
        WHERE ma.doctor_id = ? AND ma.status = "active" 
        ORDER BY ma.created_at DESC 
        LIMIT 3
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['medical_alerts'][] = [
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'alert_type' => $row['alert_type'] ?? 'general',
            'message' => $row['message'] ?? '',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default alerts
    $defaults['medical_alerts'] = [
        ['patient_name' => 'System', 'patient_id' => '-', 'alert_type' => 'info', 'message' => 'No medical alerts at this time', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Reuse your existing styles with corrected paths -->
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #28a745;
            --primary-dark: #1e7e34;
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
            background: linear-gradient(135deg, #e8f5e8 0%, #d4e7d4 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .content-wrapper {
            margin-left: var(--sidebar-width, 280px);
            padding: 2rem;
            min-height: 100vh;
            transition: var(--transition);
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
                margin-top: 70px;
            }
        }
        
        /* Dashboard Header Styling */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .dashboard-title {
            font-size: 1.8rem;
            color: var(--primary);
            margin: 0;
        }
        
        .dashboard-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary {
            background-color: #f3f4f6;
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: #e5e7eb;
            color: var(--dark);
            text-decoration: none;
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }
        
        .info-card h2 {
            font-size: 1.4rem;
            color: #333;
            margin-top: 0;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--card-color, var(--primary));
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card.appointments { --card-color: #28a745; }
        .stat-card.consultations { --card-color: #17a2b8; }
        .stat-card.patients { --card-color: #6f42c1; }
        .stat-card.prescriptions { --card-color: #fd7e14; }
        .stat-card.total { --card-color: #007bff; }
        .stat-card.followups { --card-color: #dc3545; }

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
        .action-card.purple { --card-color: #6f42c1; }
        .action-card.orange { --card-color: #fd7e14; }
        .action-card.teal { --card-color: #20c997; }
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

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .schedule-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .schedule-table td {
            color: var(--secondary);
        }

        /* Card entry animation */
        @keyframes slideInRight {
            from {
                transform: translateX(30px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .animated-card {
            animation: slideInRight 0.5s ease-out forwards;
            opacity: 0;
        }
        
        .animated-card:nth-child(1) {
            animation-delay: 0.2s;
        }
        
        .animated-card:nth-child(2) {
            animation-delay: 0.4s;
        }
        
        .animated-card:nth-child(3) {
            animation-delay: 0.6s;
        }
        
        .animated-card:nth-child(4) {
            animation-delay: 0.8s;
        }
        
        .animated-card:nth-child(5) {
            animation-delay: 1.0s;
        }
        
        .animated-card:nth-child(6) {
            animation-delay: 1.2s;
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
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
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

        /* Patient List */
        .patient-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .patient-name {
            font-weight: 600;
            color: var(--dark);
        }

        .patient-details {
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
    $sidebar_path = $root_path . '/includes/sidebar_doctor.php';
    if (file_exists($sidebar_path)) {
        include $sidebar_path;
    } else {
        error_log('Doctor Dashboard: Sidebar file not found at ' . $sidebar_path);
    }
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, Dr. <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>Medical Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="patient_consultations.php" class="btn btn-primary">
                    <i class="fas fa-stethoscope"></i> New Consultation
                </a>
                <a href="prescriptions.php" class="btn btn-secondary">
                    <i class="fas fa-prescription"></i> Prescriptions
                </a>
            </div>
        </section>
        
        <!-- Assignment Warning (if applicable) -->
        <?php if (!empty($assignment_warning)): ?>
        <section class="info-card" style="border-left-color: #ffc107; background: #fff3cd;">
            <h2 style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> Station Assignment Notice</h2>
            <p style="color: #856404;"><?php echo htmlspecialchars($assignment_warning); ?></p>
        </section>
        <?php endif; ?>
        
        <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-heartbeat"></i> Medical Practice Overview</h2>
            <p>Welcome to your medical dashboard. Here's a quick overview of your appointments and patient care statistics.</p>
        </section>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Today's Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card appointments">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['today_appointments']); ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            <div class="stat-card consultations">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_consultations']); ?></div>
                <div class="stat-label">Pending Consultations</div>
            </div>
            <div class="stat-card patients">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['patients_seen_today']); ?></div>
                <div class="stat-label">Patients Seen Today</div>
            </div>
            <div class="stat-card prescriptions">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_prescriptions']); ?></div>
                <div class="stat-label">Pending Prescriptions</div>
            </div>
            <div class="stat-card total">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_patients']); ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card followups">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['follow_ups_due']); ?></div>
                <div class="stat-label">Follow-ups Due</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="../clinical/consultations.php" class="action-card blue">
                <i class="fas fa-stethoscope icon"></i>
                <h3>New Consultation</h3>
                <p>Start a new patient consultation and examination</p>
            </a>
            <a href="../appointment/doctor_schedule.php" class="action-card purple">
                <i class="fas fa-calendar-alt icon"></i>
                <h3>View Schedule</h3>
                <p>Check your appointment schedule and manage bookings</p>
            </a>
            <a href="../patient/medical_records.php" class="action-card orange">
                <i class="fas fa-notes-medical icon"></i>
                <h3>Patient Records</h3>
                <p>Access and update patient medical records</p>
            </a>
            <a href="../prescription/prescriptions.php" class="action-card teal">
                <i class="fas fa-prescription icon"></i>
                <h3>Prescriptions</h3>
                <p>Manage and create patient prescriptions</p>
            </a>
            <a href="../laboratory/lab_orders.php" class="action-card green">
                <i class="fas fa-vials icon"></i>
                <h3>Lab Orders</h3>
                <p>Order laboratory tests and view results</p>
            </a>
            <a href="../referral/referrals.php" class="action-card red">
                <i class="fas fa-share icon"></i>
                <h3>Referrals</h3>
                <p>Create referrals to specialists and other facilities</p>
            </a>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Today's Schedule -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
                        <a href="../appointment/doctor_schedule.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['todays_schedule']) && $defaults['todays_schedule'][0]['patient_name'] !== 'No appointments'): ?>
                        <div class="table-wrapper">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['todays_schedule'] as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['time']); ?></td>
                                            <td>
                                                <div class="patient-name"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                                <div class="patient-details"><?php echo htmlspecialchars($appointment['patient_id']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['type']); ?></td>
                                            <td><span class="status-badge status-<?php echo $appointment['status']; ?>"><?php echo ucfirst($appointment['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No appointments scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Patients -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-user-friends"></i> Recent Patients</h3>
                        <a href="../patient/patient_list.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['recent_patients']) && $defaults['recent_patients'][0]['patient_name'] !== 'No recent patients'): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['recent_patients'] as $patient): ?>
                                <div class="patient-item">
                                    <div class="patient-name"><?php echo htmlspecialchars($patient['patient_name']); ?></div>
                                    <div class="patient-details">
                                        ID: <?php echo htmlspecialchars($patient['patient_id']); ?> • 
                                        Last Visit: <?php echo htmlspecialchars($patient['last_visit']); ?> • 
                                        Diagnosis: <?php echo htmlspecialchars($patient['diagnosis']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-friends"></i>
                            <p>No recent patient visits</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Medical Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Medical Alerts</h3>
                        <a href="../clinical/medical_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['medical_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['medical_alerts'] as $alert): ?>
                                <div class="patient-item">
                                    <div class="patient-name">
                                        <?php echo htmlspecialchars($alert['patient_name']); ?>
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </div>
                                    <div class="patient-details">
                                        <?php echo htmlspecialchars($alert['message']); ?><br>
                                        <small><?php echo htmlspecialchars($alert['date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No medical alerts</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Stats -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-chart-pie"></i> Quick Statistics</h3>
                    </div>
                    
                    <div style="padding: 1rem 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <strong>Average Consultation Time</strong>
                            <span>25 min</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <strong>This Week's Patients</strong>
                            <span><?php echo number_format($defaults['stats']['total_patients']); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <strong>Prescriptions Written</strong>
                            <span><?php echo number_format($defaults['stats']['pending_prescriptions'] + 15); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
                            <strong>Patient Satisfaction</strong>
                            <span class="status-badge status-completed">98%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Simple animation for the cards
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation classes to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.classList.add('animated-card');
            });
        });
    </script>
</body>

</html>
