<?php
// dashboard_nurse.php
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
    error_log('Nurse Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'nurse') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to nurse dashboard - User: ' . $_SESSION['employee_id'] . ' with role: ' . 
              ($_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}

// Log session data for debugging
error_log('Nurse Dashboard - Session Data: ' . print_r($_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once $root_path . '/config/db.php';

// Debug connection status
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// -------------------- Data bootstrap (Nurse Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'patients_assigned' => 0,
        'vitals_recorded_today' => 0,
        'medications_administered' => 0,
        'nursing_notes_written' => 0,
        'pending_tasks' => 0,
        'shift_hours' => 8
    ],
    'assigned_patients' => [],
    'vitals_due' => [],
    'medication_schedule' => [],
    'nursing_alerts' => []
];

// Get nurse info from employees table
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
    error_log("Nurse dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Patients Assigned to this nurse
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM patient_assignments WHERE nurse_id = ? AND status = "active"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['patients_assigned'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Vitals recorded today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM vital_signs WHERE DATE(recorded_date) = ? AND recorded_by = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['vitals_recorded_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Medications administered today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM medication_administration WHERE DATE(administered_date) = ? AND nurse_id = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['medications_administered'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Nursing notes written today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM nursing_notes WHERE DATE(note_date) = ? AND nurse_id = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['nursing_notes_written'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Pending nursing tasks
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM nursing_tasks WHERE nurse_id = ? AND status = "pending"');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_tasks'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Assigned Patients
try {
    $stmt = $pdo->prepare('
        SELECT pa.patient_id, p.first_name, p.last_name, p.room_number, pa.admission_date, pa.condition_severity
        FROM patient_assignments pa 
        JOIN patients p ON pa.patient_id = p.patient_id 
        WHERE pa.nurse_id = ? AND pa.status = "active" 
        ORDER BY pa.condition_severity DESC, pa.admission_date ASC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['assigned_patients'][] = [
            'patient_id' => $row['patient_id'],
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'room_number' => $row['room_number'] ?? 'N/A',
            'admission_date' => date('M d, Y', strtotime($row['admission_date'])),
            'condition_severity' => $row['condition_severity'] ?? 'stable'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['assigned_patients'] = [
        ['patient_id' => '-', 'patient_name' => 'No patients assigned', 'room_number' => '-', 'admission_date' => '-', 'condition_severity' => 'stable']
    ];
}

// Vitals Due
try {
    $stmt = $pdo->prepare('
        SELECT vr.patient_id, p.first_name, p.last_name, p.room_number, vr.vital_type, vr.scheduled_time
        FROM vital_schedules vr 
        JOIN patients p ON vr.patient_id = p.patient_id 
        WHERE vr.nurse_id = ? AND DATE(vr.scheduled_date) = ? AND vr.status = "pending" 
        ORDER BY vr.scheduled_time ASC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id, date('Y-m-d')]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['vitals_due'][] = [
            'patient_id' => $row['patient_id'],
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'room_number' => $row['room_number'] ?? 'N/A',
            'vital_type' => $row['vital_type'] ?? 'Basic Vitals',
            'scheduled_time' => date('H:i', strtotime($row['scheduled_time']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['vitals_due'] = [
        ['patient_id' => '-', 'patient_name' => 'No vitals due', 'room_number' => '-', 'vital_type' => '-', 'scheduled_time' => '-']
    ];
}

// Medication Schedule
try {
    $stmt = $pdo->prepare('
        SELECT ms.patient_id, p.first_name, p.last_name, p.room_number, ms.medication_name, ms.scheduled_time, ms.dosage
        FROM medication_schedules ms 
        JOIN patients p ON ms.patient_id = p.patient_id 
        WHERE ms.nurse_id = ? AND DATE(ms.scheduled_date) = ? AND ms.status = "pending" 
        ORDER BY ms.scheduled_time ASC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id, date('Y-m-d')]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['medication_schedule'][] = [
            'patient_id' => $row['patient_id'],
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'room_number' => $row['room_number'] ?? 'N/A',
            'medication_name' => $row['medication_name'] ?? 'Medication',
            'dosage' => $row['dosage'] ?? 'As prescribed',
            'scheduled_time' => date('H:i', strtotime($row['scheduled_time']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['medication_schedule'] = [
        ['patient_id' => '-', 'patient_name' => 'No medications due', 'room_number' => '-', 'medication_name' => '-', 'dosage' => '-', 'scheduled_time' => '-']
    ];
}

// Nursing Alerts
try {
    $stmt = $pdo->prepare('
        SELECT na.patient_id, p.first_name, p.last_name, na.alert_type, na.message, na.created_at
        FROM nursing_alerts na 
        JOIN patients p ON na.patient_id = p.patient_id 
        WHERE na.nurse_id = ? AND na.status = "active" 
        ORDER BY na.created_at DESC 
        LIMIT 3
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['nursing_alerts'][] = [
            'patient_id' => $row['patient_id'],
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'alert_type' => $row['alert_type'] ?? 'general',
            'message' => $row['message'] ?? '',
            'date' => date('m/d/Y H:i', strtotime($row['created_at']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default alerts
    $defaults['nursing_alerts'] = [
        ['patient_id' => '-', 'patient_name' => 'System', 'alert_type' => 'info', 'message' => 'No nursing alerts at this time', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Nurse Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Reuse your existing styles with corrected paths -->
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #8e44ad;
            --primary-light: #a855c7;
            --primary-dark: #7d3c98;
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
            background: rgba(var(--card-color-rgb, 142, 68, 173), 0.1);
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

        .stat-card.patients { --card-color: #8e44ad; --card-color-rgb: 142, 68, 173; }
        .stat-card.vitals { --card-color: #e74c3c; --card-color-rgb: 231, 76, 60; }
        .stat-card.medications { --card-color: #3498db; --card-color-rgb: 52, 152, 219; }
        .stat-card.notes { --card-color: #f39c12; --card-color-rgb: 243, 156, 18; }
        .stat-card.tasks { --card-color: #2ecc71; --card-color-rgb: 46, 204, 113; }
        .stat-card.shift { --card-color: #95a5a6; --card-color-rgb: 149, 165, 166; }

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
            background: rgba(var(--card-color-rgb, 142, 68, 173), 0.1);
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

        .action-card.red { --card-color: #e74c3c; --card-color-rgb: 231, 76, 60; }
        .action-card.blue { --card-color: #3498db; --card-color-rgb: 52, 152, 219; }
        .action-card.green { --card-color: #2ecc71; --card-color-rgb: 46, 204, 113; }
        .action-card.orange { --card-color: #f39c12; --card-color-rgb: 243, 156, 18; }
        .action-card.purple { --card-color: #8e44ad; --card-color-rgb: 142, 68, 173; }
        .action-card.teal { --card-color: #1abc9c; --card-color-rgb: 26, 188, 156; }

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

        .item-time {
            background: rgba(var(--primary-rgb, 142, 68, 173), 0.1);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 600;
        }

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

        .alert-message {
            margin: 0.5rem 0;
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--dark);
        }

        /* Badges */
        .severity-stable {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
        }

        .severity-moderate {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
        }

        .severity-critical {
            background: rgba(220, 53, 69, 0.15);
            color: #721c24;
        }

        .alert-critical {
            background: rgba(220, 53, 69, 0.15);
            color: #721c24;
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #856404;
        }

        .alert-info {
            background: rgba(23, 162, 184, 0.15);
            color: #0c5460;
        }

        .time-badge {
            background: var(--light);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
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
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }

            .content-columns {
                grid-template-columns: 1fr;
            }

            .item-details {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>

    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include $root_path . '/includes/sidebar_nurse.php';
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, Nurse <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>Nursing Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="../clinical/vital_signs.php" class="btn btn-primary">
                    <i class="fas fa-heartbeat"></i> Record Vitals
                </a>
                <a href="../clinical/medication_administration.php" class="btn btn-secondary">
                    <i class="fas fa-pills"></i> Administer Meds
                </a>
                <a href="../auth/employee_logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </section>
        
        <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-clipboard-check"></i> Nursing Care Overview</h2>
            <p>Welcome to your nursing dashboard. Here you can access tools for patient care, medication administration, and health records management.</p>
        </section>

        <!-- Statistics Cards -->
        <section class="stats-section">
            <h2 class="section-heading"><i class="fas fa-chart-line"></i> Today's Overview</h2>
            
            <div class="stats-grid">
                <div class="stat-card patients animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['patients_assigned']); ?></div>
                        <div class="stat-label">Patients Assigned</div>
                    </div>
                </div>
                
                <div class="stat-card vitals animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['vitals_recorded_today']); ?></div>
                        <div class="stat-label">Vitals Recorded Today</div>
                    </div>
                </div>
                
                <div class="stat-card medications animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['medications_administered']); ?></div>
                        <div class="stat-label">Medications Given</div>
                    </div>
                </div>
                
                <div class="stat-card notes animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="stat-icon"><i class="fas fa-notes-medical"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['nursing_notes_written']); ?></div>
                        <div class="stat-label">Nursing Notes</div>
                    </div>
                </div>
                
                <div class="stat-card tasks animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['pending_tasks']); ?></div>
                        <div class="stat-label">Pending Tasks</div>
                    </div>
                </div>
                
                <div class="stat-card shift animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['shift_hours']); ?>h</div>
                        <div class="stat-label">Shift Duration</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="quick-actions-section">
            <h2 class="section-heading"><i class="fas fa-bolt"></i> Quick Actions</h2>
            
            <div class="action-grid">
                <a href="../clinical/vital_signs.php" class="action-card red animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="action-icon"><i class="fas fa-heartbeat"></i></div>
                    <div class="action-content">
                        <h3>Record Vitals</h3>
                        <p>Record patient vital signs and measurements</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../clinical/medication_administration.php" class="action-card blue animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="action-icon"><i class="fas fa-pills"></i></div>
                    <div class="action-content">
                        <h3>Medication Administration</h3>
                        <p>Administer medications and update charts</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../clinical/nursing_notes.php" class="action-card orange animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="action-icon"><i class="fas fa-notes-medical"></i></div>
                    <div class="action-content">
                        <h3>Nursing Notes</h3>
                        <p>Write and update nursing observations</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../patient/patient_assessment.php" class="action-card purple animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="action-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="action-content">
                        <h3>Patient Assessment</h3>
                        <p>Conduct comprehensive assessments</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../clinical/care_plans.php" class="action-card green animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="action-icon"><i class="fas fa-file-medical"></i></div>
                    <div class="action-content">
                        <h3>Care Plans</h3>
                        <p>Review and update patient care plans</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../clinical/wound_care.php" class="action-card teal animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="action-icon"><i class="fas fa-band-aid"></i></div>
                    <div class="action-content">
                        <h3>Wound Care</h3>
                        <p>Document wound care and treatment</p>
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
                    <!-- Assigned Patients -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-user-friends"></i> Assigned Patients</h3>
                            <a href="../patient/patient_assignments.php" class="card-action-link">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['assigned_patients']) && $defaults['assigned_patients'][0]['patient_name'] !== 'No patients assigned'): ?>
                                <div class="data-list">
                                    <?php foreach ($defaults['assigned_patients'] as $patient): ?>
                                        <div class="list-item patient-item">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <?php echo htmlspecialchars($patient['patient_name']); ?>
                                                </div>
                                                <div class="item-badge severity-<?php echo $patient['condition_severity']; ?>">
                                                    <?php echo ucfirst($patient['condition_severity']); ?>
                                                </div>
                                            </div>
                                            <div class="item-details">
                                                <span><i class="fas fa-hospital"></i> Room: <?php echo htmlspecialchars($patient['room_number']); ?></span>
                                                <span><i class="fas fa-id-card"></i> ID: <?php echo htmlspecialchars($patient['patient_id']); ?></span>
                                                <span><i class="fas fa-calendar-check"></i> Admitted: <?php echo htmlspecialchars($patient['admission_date']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-friends"></i>
                                    <p>No patients currently assigned</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Vitals Due -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-heartbeat"></i> Vitals Due Today</h3>
                            <a href="../clinical/vital_schedule.php" class="card-action-link">
                                View Schedule <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['vitals_due']) && $defaults['vitals_due'][0]['patient_name'] !== 'No vitals due'): ?>
                                <div class="responsive-table">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Patient</th>
                                                <th>Room</th>
                                                <th>Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($defaults['vitals_due'] as $vital): ?>
                                                <tr>
                                                    <td><span class="time-badge"><?php echo htmlspecialchars($vital['scheduled_time']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($vital['patient_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($vital['room_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($vital['vital_type']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-heartbeat"></i>
                                    <p>No vitals scheduled for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="content-column side-column animate-on-scroll" data-animation="fade-left" data-delay="200">
                    <!-- Medication Schedule -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-pills"></i> Medication Schedule</h3>
                            <a href="../clinical/medication_schedule.php" class="card-action-link">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['medication_schedule']) && $defaults['medication_schedule'][0]['patient_name'] !== 'No medications due'): ?>
                                <div class="data-list">
                                    <?php foreach ($defaults['medication_schedule'] as $medication): ?>
                                        <div class="list-item medication-item">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <?php echo htmlspecialchars($medication['medication_name']); ?>
                                                </div>
                                                <div class="item-time">
                                                    <?php echo htmlspecialchars($medication['scheduled_time']); ?>
                                                </div>
                                            </div>
                                            <div class="item-details">
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($medication['patient_name']); ?></span>
                                                <span><i class="fas fa-hospital"></i> Room: <?php echo htmlspecialchars($medication['room_number']); ?></span>
                                                <span><i class="fas fa-prescription"></i> Dose: <?php echo htmlspecialchars($medication['dosage']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-pills"></i>
                                    <p>No medications scheduled</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Nursing Alerts -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3><i class="fas fa-bell"></i> Nursing Alerts</h3>
                            <a href="../clinical/nursing_alerts.php" class="card-action-link">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="content-card-body">
                            <?php if (!empty($defaults['nursing_alerts'])): ?>
                                <div class="data-list">
                                    <?php foreach ($defaults['nursing_alerts'] as $alert): ?>
                                        <div class="list-item alert-item">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <?php echo htmlspecialchars($alert['patient_name']); ?>
                                                </div>
                                                <div class="item-badge alert-<?php echo $alert['alert_type']; ?>">
                                                    <?php echo ucfirst($alert['alert_type']); ?>
                                                </div>
                                            </div>
                                            <div class="alert-message">
                                                <?php echo htmlspecialchars($alert['message']); ?>
                                            </div>
                                            <div class="item-details">
                                                <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($alert['date']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-shield-alt"></i>
                                    <p>No nursing alerts</p>
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
