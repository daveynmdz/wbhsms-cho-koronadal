<?php
// dashboard_pharmacist.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not a pharmacist, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../config/db.php';
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

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
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #fd7e14;
            --primary-dark: #e8650e;
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
            background: linear-gradient(135deg, #fff5e6 0%, #ffe5cc 100%);
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
        .stat-card.dispensed { --card-color: #28a745; }
        .stat-card.stock { --card-color: #dc3545; }
        .stat-card.review { --card-color: #6f42c1; }
        .stat-card.medications { --card-color: #17a2b8; }
        .stat-card.revenue { --card-color: #fd7e14; }

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

        .action-card.orange { --card-color: #fd7e14; }
        .action-card.blue { --card-color: #007bff; }
        .action-card.green { --card-color: #28a745; }
        .action-card.purple { --card-color: #6f42c1; }
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

        .pharmacy-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pharmacy-table th,
        .pharmacy-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
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
    include '../../includes/sidebar_pharmacist.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, Pharmacist <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                Pharmacy Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Pharmacy Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-prescription"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_prescriptions']); ?></div>
                <div class="stat-label">Pending Prescriptions</div>
            </div>
            <div class="stat-card dispensed">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-hand-holding-medical"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['dispensed_today']); ?></div>
                <div class="stat-label">Dispensed Today</div>
            </div>
            <div class="stat-card stock">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['low_stock_items']); ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            <div class="stat-card review">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['prescription_reviews']); ?></div>
                <div class="stat-label">Needing Review</div>
            </div>
            <div class="stat-card medications">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['total_medications']); ?></div>
                <div class="stat-label">Total Medications</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                </div>
                <div class="stat-number">₱<?php echo number_format($defaults['stats']['revenue_today'], 2); ?></div>
                <div class="stat-label">Revenue Today</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="../prescription/dispense_medication.php" class="action-card orange">
                <i class="fas fa-pills icon"></i>
                <h3>Dispense Medication</h3>
                <p>Process and dispense patient prescriptions</p>
            </a>
            <a href="../prescription/prescription_review.php" class="action-card blue">
                <i class="fas fa-clipboard-check icon"></i>
                <h3>Review Prescriptions</h3>
                <p>Verify and approve pending prescriptions</p>
            </a>
            <a href="../prescription/drug_interaction.php" class="action-card purple">
                <i class="fas fa-shield-alt icon"></i>
                <h3>Drug Interaction Check</h3>
                <p>Check for potential drug interactions and allergies</p>
            </a>
            <a href="../prescription/inventory_management.php" class="action-card green">
                <i class="fas fa-boxes icon"></i>
                <h3>Inventory Management</h3>
                <p>Manage medication stock and supplies</p>
            </a>
            <a href="../prescription/counseling.php" class="action-card teal">
                <i class="fas fa-user-md icon"></i>
                <h3>Patient Counseling</h3>
                <p>Provide medication counseling to patients</p>
            </a>
            <a href="../prescription/pharmacy_reports.php" class="action-card red">
                <i class="fas fa-chart-bar icon"></i>
                <h3>Pharmacy Reports</h3>
                <p>Generate inventory and dispensing reports</p>
            </a>
        </div>

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
    </section>
</body>

</html>
