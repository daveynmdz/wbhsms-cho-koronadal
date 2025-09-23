<?php
// dashboard_nurse.php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If user is not logged in or not a nurse, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'nurse') {
    header('Location: ../auth/employee_login.php');
    exit();
}

// DB
require_once '../../config/db.php';
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
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary: #8e44ad;
            --primary-dark: #7d3c98;
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
            background: linear-gradient(135deg, #f4e6ff 0%, #e8d5ff 100%);
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

        .stat-card.patients { --card-color: #8e44ad; }
        .stat-card.vitals { --card-color: #e74c3c; }
        .stat-card.medications { --card-color: #3498db; }
        .stat-card.notes { --card-color: #f39c12; }
        .stat-card.tasks { --card-color: #2ecc71; }
        .stat-card.shift { --card-color: #95a5a6; }

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

        .action-card.purple { --card-color: #8e44ad; }
        .action-card.red { --card-color: #e74c3c; }
        .action-card.blue { --card-color: #3498db; }
        .action-card.orange { --card-color: #f39c12; }
        .action-card.green { --card-color: #2ecc71; }
        .action-card.teal { --card-color: #1abc9c; }

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

        .severity-stable {
            background: #d1ecf1;
            color: #0c5460;
        }

        .severity-moderate {
            background: #fff3cd;
            color: #856404;
        }

        .severity-critical {
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

        /* Patient Item */
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

        /* Medication Item */
        .medication-item {
            padding: 0.75rem;
            border-left: 3px solid #3498db;
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
        }

        .medication-name {
            font-weight: 600;
            color: var(--dark);
        }

        .medication-details {
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
    include '../../includes/sidebar_nurse.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Good day, Nurse <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">
                Nursing Dashboard • <?php echo htmlspecialchars($defaults['role']); ?> 
                • ID: <?php echo htmlspecialchars($defaults['employee_number']); ?>
            </p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Today's Overview
        </h2>
        <div class="stats-grid">
            <div class="stat-card patients">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['patients_assigned']); ?></div>
                <div class="stat-label">Patients Assigned</div>
            </div>
            <div class="stat-card vitals">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['vitals_recorded_today']); ?></div>
                <div class="stat-label">Vitals Recorded Today</div>
            </div>
            <div class="stat-card medications">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['medications_administered']); ?></div>
                <div class="stat-label">Medications Given</div>
            </div>
            <div class="stat-card notes">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-notes-medical"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['nursing_notes_written']); ?></div>
                <div class="stat-label">Nursing Notes</div>
            </div>
            <div class="stat-card tasks">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['pending_tasks']); ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
            <div class="stat-card shift">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($defaults['stats']['shift_hours']); ?>h</div>
                <div class="stat-label">Shift Duration</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
        <div class="action-grid">
            <a href="../clinical/vital_signs.php" class="action-card red">
                <i class="fas fa-heartbeat icon"></i>
                <h3>Record Vitals</h3>
                <p>Record patient vital signs and measurements</p>
            </a>
            <a href="../clinical/medication_administration.php" class="action-card blue">
                <i class="fas fa-pills icon"></i>
                <h3>Medication Administration</h3>
                <p>Administer medications and update patient charts</p>
            </a>
            <a href="../clinical/nursing_notes.php" class="action-card orange">
                <i class="fas fa-notes-medical icon"></i>
                <h3>Nursing Notes</h3>
                <p>Write and update nursing observations and care notes</p>
            </a>
            <a href="../patient/patient_assessment.php" class="action-card purple">
                <i class="fas fa-clipboard-check icon"></i>
                <h3>Patient Assessment</h3>
                <p>Conduct comprehensive patient assessments</p>
            </a>
            <a href="../clinical/care_plans.php" class="action-card green">
                <i class="fas fa-file-medical icon"></i>
                <h3>Care Plans</h3>
                <p>Review and update patient care plans</p>
            </a>
            <a href="../clinical/wound_care.php" class="action-card teal">
                <i class="fas fa-band-aid icon"></i>
                <h3>Wound Care</h3>
                <p>Document wound care and treatment progress</p>
            </a>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Assigned Patients -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-user-friends"></i> Assigned Patients</h3>
                        <a href="../patient/patient_assignments.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['assigned_patients']) && $defaults['assigned_patients'][0]['patient_name'] !== 'No patients assigned'): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['assigned_patients'] as $patient): ?>
                                <div class="patient-item">
                                    <div class="patient-name">
                                        <?php echo htmlspecialchars($patient['patient_name']); ?>
                                        <span class="status-badge severity-<?php echo $patient['condition_severity']; ?>"><?php echo ucfirst($patient['condition_severity']); ?></span>
                                    </div>
                                    <div class="patient-details">
                                        Room: <?php echo htmlspecialchars($patient['room_number']); ?> • 
                                        ID: <?php echo htmlspecialchars($patient['patient_id']); ?> • 
                                        Admitted: <?php echo htmlspecialchars($patient['admission_date']); ?>
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

                <!-- Vitals Due -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-heartbeat"></i> Vitals Due</h3>
                        <a href="../clinical/vital_schedule.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['vitals_due']) && $defaults['vitals_due'][0]['patient_name'] !== 'No vitals due'): ?>
                        <div class="table-wrapper">
                            <table class="schedule-table">
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
                                            <td><?php echo htmlspecialchars($vital['scheduled_time']); ?></td>
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

            <!-- Right Column -->
            <div class="right-column">
                <!-- Medication Schedule -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-pills"></i> Medication Schedule</h3>
                        <a href="../clinical/medication_schedule.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['medication_schedule']) && $defaults['medication_schedule'][0]['patient_name'] !== 'No medications due'): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['medication_schedule'] as $medication): ?>
                                <div class="medication-item">
                                    <div class="medication-name">
                                        <?php echo htmlspecialchars($medication['medication_name']); ?>
                                        <span style="font-weight: normal; color: var(--secondary);">at <?php echo htmlspecialchars($medication['scheduled_time']); ?></span>
                                    </div>
                                    <div class="medication-details">
                                        Patient: <?php echo htmlspecialchars($medication['patient_name']); ?> • 
                                        Room: <?php echo htmlspecialchars($medication['room_number']); ?> • 
                                        Dose: <?php echo htmlspecialchars($medication['dosage']); ?>
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

                <!-- Nursing Alerts -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-bell"></i> Nursing Alerts</h3>
                        <a href="../clinical/nursing_alerts.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['nursing_alerts'])): ?>
                        <div class="table-wrapper">
                            <?php foreach ($defaults['nursing_alerts'] as $alert): ?>
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
                            <p>No nursing alerts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</body>

</html>
