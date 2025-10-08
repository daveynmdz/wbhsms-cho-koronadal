<?php
// dashboard_pharmacist.php
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
    error_log('Pharmacist Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'pharmacist') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to pharmacist dashboard - User: ' . $_SESSION['employee_id'] . ' with role: ' . 
              ($_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset($_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}


// Log session data for debugging
error_log('Pharmacist Dashboard - Session Data: ' . print_r($_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/staff_assignment.php';

// Debug connection status
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Enforce staff assignment for today
$assignment = getStaffAssignment($employee_id);
if (!$assignment) {
    // Not assigned today, block access
    error_log('Pharmacist Dashboard: No active staff assignment for today.');
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'You are not assigned to any station today. Please contact the administrator.');
    header('Location: ../auth/employee_login.php?not_assigned=1');
    exit();
}

// -------------------- Data bootstrap (Pharmacist Dashboard) --------------------
$defaults = [
    'name' => $_SESSION['employee_first_name'] . ' ' . $_SESSION['employee_last_name'],
    'employee_number' => $_SESSION['employee_number'] ?? '-',
    'role' => $employee_role,
    'stats' => [
        'pending_prescriptions' => 0,
        'dispensed_today' => 0,
        'low_stock_items' => 0,
        'prescription_reviews' => 0,
        'total_medications' => 0,
        'revenue_today' => 0
    ],
    'pending_prescriptions' => [],
    'recent_dispensed' => [],
    'inventory_alerts' => [],
    'pharmacy_alerts' => []
];

// Get pharmacist info from employees table
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
    error_log("Pharmacist dashboard error: " . $e->getMessage());
}

// Dashboard Statistics
try {
    // Pending Prescriptions
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM prescriptions WHERE status = "pending" AND pharmacist_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['pending_prescriptions'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Dispensed Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM prescriptions WHERE DATE(dispensed_date) = ? AND pharmacist_id = ? AND status = "dispensed"');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['dispensed_today'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Low Stock Items
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM pharmacy_inventory WHERE quantity <= reorder_level');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['low_stock_items'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Prescriptions Needing Review
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM prescriptions WHERE status = "needs_review" AND pharmacist_id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['prescription_reviews'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Total Medications in Inventory
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM pharmacy_inventory WHERE quantity > 0');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['total_medications'] = $row['count'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

try {
    // Revenue Today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT SUM(total_amount) as total FROM prescription_billing WHERE DATE(billing_date) = ? AND pharmacist_id = ?');
    $stmt->execute([$today, $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $defaults['stats']['revenue_today'] = $row['total'] ?? 0;
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Pending Prescriptions
try {
    $stmt = $pdo->prepare('
        SELECT p.prescription_id, p.medication_name, p.dosage, p.quantity, p.prescribed_date,
               pt.first_name, pt.last_name, pt.patient_id, p.priority
        FROM prescriptions p 
        JOIN patients pt ON p.patient_id = pt.patient_id 
        WHERE p.status = "pending" AND p.pharmacist_id = ? 
        ORDER BY p.priority DESC, p.prescribed_date ASC 
        LIMIT 8
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['pending_prescriptions'][] = [
            'prescription_id' => $row['prescription_id'],
            'medication_name' => $row['medication_name'] ?? 'Medication',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'dosage' => $row['dosage'] ?? '1 tablet',
            'quantity' => $row['quantity'] ?? 30,
            'priority' => $row['priority'] ?? 'normal',
            'prescribed_date' => date('M d, Y', strtotime($row['prescribed_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['pending_prescriptions'] = [
        ['prescription_id' => '-', 'medication_name' => 'No pending prescriptions', 'patient_name' => '-', 'patient_id' => '-', 'dosage' => '-', 'quantity' => 0, 'priority' => 'normal', 'prescribed_date' => '-']
    ];
}

// Recent Dispensed
try {
    $stmt = $pdo->prepare('
        SELECT p.prescription_id, p.medication_name, p.quantity, p.dispensed_date,
               pt.first_name, pt.last_name, pt.patient_id
        FROM prescriptions p 
        JOIN patients pt ON p.patient_id = pt.patient_id 
        WHERE p.pharmacist_id = ? AND p.status = "dispensed" 
        ORDER BY p.dispensed_date DESC 
        LIMIT 5
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['recent_dispensed'][] = [
            'prescription_id' => $row['prescription_id'],
            'medication_name' => $row['medication_name'] ?? 'Medication',
            'patient_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'patient_id' => $row['patient_id'],
            'quantity' => $row['quantity'] ?? 30,
            'dispensed_date' => date('M d, Y H:i', strtotime($row['dispensed_date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default data
    $defaults['recent_dispensed'] = [
        ['prescription_id' => '-', 'medication_name' => 'No recent dispensing', 'patient_name' => '-', 'patient_id' => '-', 'quantity' => 0, 'dispensed_date' => '-']
    ];
}

// Inventory Alerts
try {
    $stmt = $pdo->prepare('
        SELECT medication_name, quantity, reorder_level, expiry_date, batch_number
        FROM pharmacy_inventory 
        WHERE quantity <= reorder_level OR expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY quantity ASC, expiry_date ASC 
        LIMIT 5
    ');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alert_type = 'low_stock';
        $message = 'Low stock: ' . $row['quantity'] . ' units remaining';
        
        if ($row['expiry_date'] && strtotime($row['expiry_date']) <= strtotime('+30 days')) {
            $alert_type = 'expiring';
            $message = 'Expiring on ' . date('M d, Y', strtotime($row['expiry_date']));
        }
        
        $defaults['inventory_alerts'][] = [
            'medication_name' => $row['medication_name'] ?? 'Medication',
            'alert_type' => $alert_type,
            'message' => $message,
            'quantity' => $row['quantity'] ?? 0,
            'batch_number' => $row['batch_number'] ?? '-'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; add some default alerts
    $defaults['inventory_alerts'] = [
        ['medication_name' => 'Paracetamol 500mg', 'alert_type' => 'low_stock', 'message' => 'Low stock: 25 units remaining', 'quantity' => 25, 'batch_number' => 'B2025001'],
        ['medication_name' => 'Amoxicillin 250mg', 'alert_type' => 'expiring', 'message' => 'Expiring on Oct 15, 2025', 'quantity' => 50, 'batch_number' => 'B2025002']
    ];
}

// Pharmacy Alerts
try {
    $stmt = $pdo->prepare('
        SELECT pa.alert_type, pa.message, pa.created_at, pa.priority,
               p.first_name, p.last_name, p.patient_id
        FROM pharmacy_alerts pa 
        LEFT JOIN patients p ON pa.patient_id = p.patient_id 
        WHERE pa.pharmacist_id = ? AND pa.status = "active" 
        ORDER BY pa.priority DESC, pa.created_at DESC 
        LIMIT 4
    ');
    $stmt->execute([$employee_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['pharmacy_alerts'][] = [
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
    $defaults['pharmacy_alerts'] = [
        ['alert_type' => 'warning', 'message' => 'Drug interaction alert: Check patient medication history', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'high', 'date' => date('m/d/Y H:i')],
        ['alert_type' => 'info', 'message' => 'Monthly inventory reconciliation due', 'patient_name' => 'System', 'patient_id' => '-', 'priority' => 'normal', 'date' => date('m/d/Y H:i')]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Pharmacist Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Use absolute paths for consistency -->
    <link rel="stylesheet" href="<?= $root_path ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= $root_path ?>/assets/css/sidebar.css">
    <style>
        :root {
            --primary: #fd7e14;
            --primary-light: #ff9642;
            --primary-dark: #e8650e;
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
            background: linear-gradient(135deg, #fff5e6 0%, #ffecd9 100%);
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
            background: rgba(var(--card-color-rgb, 253, 126, 20), 0.1);
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

        .stat-card.pending { --card-color: #ffc107; --card-color-rgb: 255, 193, 7; }
        .stat-card.dispensed { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .stat-card.stock { --card-color: #dc3545; --card-color-rgb: 220, 53, 69; }
        .stat-card.review { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }
        .stat-card.medications { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
        .stat-card.revenue { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }

        /* Quick Actions */
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
            background: rgba(var(--card-color-rgb, 253, 126, 20), 0.1);
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

        .action-card.orange { --card-color: #fd7e14; --card-color-rgb: 253, 126, 20; }
        .action-card.blue { --card-color: #3498db; --card-color-rgb: 52, 152, 219; }
        .action-card.green { --card-color: #28a745; --card-color-rgb: 40, 167, 69; }
        .action-card.purple { --card-color: #6f42c1; --card-color-rgb: 111, 66, 193; }
        .action-card.teal { --card-color: #17a2b8; --card-color-rgb: 23, 162, 184; }
        .action-card.red { --card-color: #dc3545; --card-color-rgb: 220, 53, 69; }

        /* Legacy info layout (converting to content-columns) */
        .info-layout {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1200px) {
            .info-layout {
                grid-template-columns: 1fr;
            }
        }

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
        
        /* Legacy card sections (keeping for compatibility) */
        .card-section {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }
        
        .card-section:hover {
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-more-btn {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            transition: var(--transition);
        }

        .view-more-btn:hover {
            color: var(--primary-dark);
        }
        
        .view-more-btn i {
            font-size: 0.75rem;
            transition: transform 0.2s;
        }

        .view-more-btn:hover i {
            transform: translateX(3px);
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
            background: rgba(var(--primary-rgb, 253, 126, 20), 0.1);
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
        
        /* Legacy tables (keeping for compatibility) */
        .table-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-light);
        }

        .pharmacy-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pharmacy-table th,
        .pharmacy-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .pharmacy-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pharmacy-table td {
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

        .alert-low_stock {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-expiring {
            background: #fff3cd;
            color: #856404;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Prescription Item */
        .prescription-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .prescription-name {
            font-weight: 600;
            color: var(--dark);
        }

        .prescription-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Inventory Item */
        .inventory-item {
            padding: 0.75rem;
            border-left: 3px solid #dc3545;
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .inventory-name {
            font-weight: 600;
            color: var(--dark);
        }

        .inventory-details {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
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
    include $root_path . '/includes/sidebar_pharmacist.php';
    ?>

    <main class="content-wrapper">
        <!-- Dashboard Header with Actions -->
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Good day, Pharmacist <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
                <p>Pharmacy Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="../prescription/dispense_medication.php" class="btn btn-primary">
                    <i class="fas fa-pills"></i> Dispense Medication
                </a>
                <a href="../prescription/inventory_management.php" class="btn btn-secondary">
                    <i class="fas fa-boxes"></i> Inventory
                </a>
            </div>
        </section>
        
        <!-- System Overview Card -->
        <section class="info-card">
            <h2><i class="fas fa-clinic-medical"></i> Pharmacy Overview</h2>
            <p>Welcome to your pharmacy dashboard. Here's a quick overview of prescriptions and inventory statistics.</p>
        </section>

        <!-- Statistics Overview -->
        <section class="stats-section">
            <h2 class="section-heading"><i class="fas fa-chart-line"></i> Pharmacy Overview</h2>
            
            <div class="stats-grid">
                <div class="stat-card pending animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['pending_prescriptions']); ?></div>
                        <div class="stat-label">Pending Prescriptions</div>
                    </div>
                </div>
                
                <div class="stat-card dispensed animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="stat-icon"><i class="fas fa-hand-holding-medical"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['dispensed_today']); ?></div>
                        <div class="stat-label">Dispensed Today</div>
                    </div>
                </div>
                
                <div class="stat-card stock animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['low_stock_items']); ?></div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                </div>
                
                <div class="stat-card review animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['prescription_reviews']); ?></div>
                        <div class="stat-label">Needing Review</div>
                    </div>
                </div>
                
                <div class="stat-card medications animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($defaults['stats']['total_medications']); ?></div>
                        <div class="stat-label">Total Medications</div>
                    </div>
                </div>
                
                <div class="stat-card revenue animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                    <div class="stat-details">
                        <div class="stat-number">₱<?php echo number_format($defaults['stats']['revenue_today'], 2); ?></div>
                        <div class="stat-label">Revenue Today</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="quick-actions-section">
            <h2 class="section-heading">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h2>
            <div class="action-grid">
                <a href="../prescription/dispense_medication.php" class="action-card orange animate-on-scroll" data-animation="fade-up" data-delay="100">
                    <div class="action-icon"><i class="fas fa-pills"></i></div>
                    <div class="action-content">
                        <h3>Dispense Medication</h3>
                        <p>Process and dispense patient prescriptions</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../prescription/prescription_review.php" class="action-card blue animate-on-scroll" data-animation="fade-up" data-delay="200">
                    <div class="action-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="action-content">
                        <h3>Review Prescriptions</h3>
                        <p>Verify and approve pending prescriptions</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../prescription/drug_interaction.php" class="action-card purple animate-on-scroll" data-animation="fade-up" data-delay="300">
                    <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="action-content">
                        <h3>Drug Interaction Check</h3>
                        <p>Check for potential drug interactions and allergies</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../prescription/inventory_management.php" class="action-card green animate-on-scroll" data-animation="fade-up" data-delay="400">
                    <div class="action-icon"><i class="fas fa-boxes"></i></div>
                    <div class="action-content">
                        <h3>Inventory Management</h3>
                        <p>Manage medication stock and supplies</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../prescription/counseling.php" class="action-card teal animate-on-scroll" data-animation="fade-up" data-delay="500">
                    <div class="action-icon"><i class="fas fa-user-md"></i></div>
                    <div class="action-content">
                        <h3>Patient Counseling</h3>
                        <p>Provide medication counseling to patients</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="../prescription/pharmacy_reports.php" class="action-card red animate-on-scroll" data-animation="fade-up" data-delay="600">
                    <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="action-content">
                        <h3>Pharmacy Reports</h3>
                        <p>Generate inventory and dispensing reports</p>
                    </div>
                    <div class="action-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
            </div>
        </section>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Pending Prescriptions -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-prescription"></i> Pending Prescriptions</h3>
                        <a href="../prescription/pending_prescriptions.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['pending_prescriptions']) && $defaults['pending_prescriptions'][0]['medication_name'] !== 'No pending prescriptions'): ?>
                        <div class="table-wrapper">
                            <table class="pharmacy-table">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Patient</th>
                                        <th>Qty</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['pending_prescriptions'] as $prescription): ?>
                                        <tr>
                                            <td>
                                                <div class="prescription-name"><?php echo htmlspecialchars($prescription['medication_name']); ?></div>
                                                <small><?php echo htmlspecialchars($prescription['dosage']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($prescription['patient_name']); ?></div>
                                                <small><?php echo htmlspecialchars($prescription['patient_id']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($prescription['quantity']); ?></td>
                                            <td><span class="status-badge priority-<?php echo $prescription['priority']; ?>"><?php echo ucfirst($prescription['priority']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending prescriptions at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Dispensed -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-hand-holding-medical"></i> Recently Dispensed</h3>
                        <a href="../prescription/dispensed_history.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['recent_dispensed']) && $defaults['recent_dispensed'][0]['medication_name'] !== 'No recent dispensing'): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['recent_dispensed'] as $dispensed): ?>
                                <div class="prescription-item">
                                    <div class="prescription-name"><?php echo htmlspecialchars($dispensed['medication_name']); ?></div>
                                    <div class="prescription-details">
                                        Patient: <?php echo htmlspecialchars($dispensed['patient_name']); ?> • 
                                        Qty: <?php echo htmlspecialchars($dispensed['quantity']); ?> • 
                                        Dispensed: <?php echo htmlspecialchars($dispensed['dispensed_date']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-pills"></i>
                            <p>No recent dispensing activity</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Inventory Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Inventory Alerts</h3>
                        <a href="../prescription/inventory_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['inventory_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['inventory_alerts'] as $alert): ?>
                                <div class="inventory-item">
                                    <div class="inventory-name">
                                        <?php echo htmlspecialchars($alert['medication_name']); ?>
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?></span>
                                    </div>
                                    <div class="inventory-details">
                                        <?php echo htmlspecialchars($alert['message']); ?> • 
                                        Batch: <?php echo htmlspecialchars($alert['batch_number']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No inventory alerts</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pharmacy Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-bell"></i> Pharmacy Alerts</h3>
                        <a href="../prescription/pharmacy_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['pharmacy_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['pharmacy_alerts'] as $alert): ?>
                                <div class="prescription-item">
                                    <div class="prescription-name">
                                        <?php echo htmlspecialchars($alert['patient_name']); ?>
                                        <span class="status-badge alert-<?php echo $alert['alert_type']; ?>"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </div>
                                    <div class="prescription-details">
                                        <?php echo htmlspecialchars($alert['message']); ?><br>
                                        <small><?php echo htmlspecialchars($alert['date']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No pharmacy alerts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
