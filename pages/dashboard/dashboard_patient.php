<?php
// dashboard_patient.php
// Use patient session configuration
require_once '../../config/session/patient_session.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
// If user is not logged in, bounce to login
if (!isset($_SESSION['patient_id'])) {
    header('Location: ../auth/patient_login.php'); // correct path to auth folder
    exit();
}

// DB
require_once '../../config/db.php'; // adjust relative path if needed
$patient_id = $_SESSION['patient_id'];

// -------------------- Data bootstrap (from patientHomepage.php) --------------------
$defaults = [
    'name' => 'Patient',
    'patient_number' => '-',
    'latest_appointment' => [
        'date' => '-',
        'complaint' => '-',
        'diagnosis' => '-',
        'treatment' => '-',
        'height' => '-',
        'weight' => '-',
        'bp' => '-',
        'cardiac_rate' => '-',
        'temperature' => '-',
        'resp_rate' => '-',
    ],
    'notifications' => [],
    'activity_log' => []
];

// Patient info
$stmt = $pdo->prepare('SELECT last_name, first_name, middle_name, suffix, username FROM patients WHERE patient_id = ?');
$stmt->execute([$patient_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $full_name = $row['first_name'];
    if (!empty($row['middle_name'])) $full_name .= ' ' . $row['middle_name'];
    $full_name .= ' ' . $row['last_name'];
    if (!empty($row['suffix'])) $full_name .= ' ' . $row['suffix'];
    $defaults['name'] = trim($full_name);
    $defaults['patient_number'] = $row['username'];
}

// Latest appointment
try {
    $stmt = $pdo->prepare('SELECT date, complaint, diagnosis, treatment, height, weight, bp, cardiac_rate, temperature, resp_rate FROM appointments WHERE patient_id = ? ORDER BY date DESC LIMIT 1');
    $stmt->execute([$patient_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $defaults['latest_appointment'] = [
            'date' => date('F d, Y', strtotime($row['date'])),
            'complaint' => $row['complaint'] ?? '-',
            'diagnosis' => $row['diagnosis'] ?? '-',
            'treatment' => $row['treatment'] ?? '-',
            'height' => $row['height'] ?? '-',
            'weight' => $row['weight'] ?? '-',
            'bp' => $row['bp'] ?? '-',
            'cardiac_rate' => $row['cardiac_rate'] ?? '-',
            'temperature' => $row['temperature'] ?? '-',
            'resp_rate' => $row['resp_rate'] ?? '-'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Fetch latest vitals for this patient
$latest_vitals = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM vitals WHERE patient_id = ? ORDER BY date_recorded DESC LIMIT 1");
    $stmt->execute([$patient_id]);
    $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // table might not exist yet; ignore
}


// Notifications (latest 5)
try {
    $stmt = $pdo->prepare('SELECT date, description, status FROM notifications WHERE patient_id = ? ORDER BY date DESC LIMIT 5');
    $stmt->execute([$patient_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['notifications'][] = [
            'date' => date('m/d/Y', strtotime($row['date'])),
            'description' => $row['description'] ?? '',
            'status' => $row['status'] ?? 'unread'
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; ignore
}

// Activity log (latest 5)
try {
    $stmt = $pdo->prepare('SELECT activity, date FROM activity_log WHERE patient_id = ? ORDER BY date DESC LIMIT 5');
    $stmt->execute([$patient_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults['activity_log'][] = [
            'activity' => $row['activity'] ?? '',
            'date' => date('m/d/Y', strtotime($row['date']))
        ];
    }
} catch (PDOException $e) {
    // table might not exist yet; ignore
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Patient Dashboard</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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

        /* Quick Actions Grid */
        .quick-actions {
            margin-bottom: 2rem;
        }

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
            justify-content: between;
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

        /* Appointment Details */
        .appointment-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .appointment-layout {
                grid-template-columns: 1fr;
            }
        }

        .detail-box {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: var(--light);
            border-radius: var(--border-radius);
            border-left: 3px solid var(--primary);
        }

        .detail-box .label {
            display: block;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .detail-box .value {
            color: var(--secondary);
            font-size: 0.95rem;
        }

        /* Vitals Grid */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem;
        }

        .vital-box {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--border-radius);
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .vital-box:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .vital-box i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .vital-box strong {
            display: block;
            font-size: 0.8rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vital-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Tables */
        .notification-table {
            width: 100%;
            border-collapse: collapse;
        }

        .notification-table th,
        .notification-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .notification-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-table td {
            color: var(--secondary);
        }

        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.unread {
            background: #fff3cd;
            color: #856404;
        }

        .status.read {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Activity Log */
        .activity-log {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-log li {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            background: var(--light);
            margin-bottom: 0.5rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            font-size: 0.9rem;
            color: var(--secondary);
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

        /* Scroll Wrappers */
        .scroll-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--border);
        }

        .scroll-wrapper::-webkit-scrollbar {
            width: 6px;
        }

        .scroll-wrapper::-webkit-scrollbar-track {
            background: var(--light);
        }

        .scroll-wrapper::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }

        /* Date Badge */
        .date-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1rem;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .appointment-layout {
                grid-template-columns: 1fr;
            }
            
            .vitals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include '../../includes/sidebar_patient.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Welcome back, <?php echo htmlspecialchars($defaults['name']); ?>!</h1>
            <p class="subtitle">City Health Office of Koronadal • Patient Portal</p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 class="section-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h2>
            <div class="action-grid">
                <a href="../appointment/appointments.php" class="action-card blue">
                    <i class="fas fa-calendar-check icon"></i>
                    <h3>Set an Appointment</h3>
                    <p>Schedule a consultation or check-up with our healthcare professionals.</p>
                </a>
                <a href="../prescription/prescriptions.php" class="action-card purple">
                    <i class="fas fa-prescription-bottle-alt icon"></i>
                    <h3>View Prescription</h3>
                    <p>Access your prescribed medications and treatment plans.</p>
                </a>
                <a href="../laboratory/lab_tests.php" class="action-card orange">
                    <i class="fas fa-vials icon"></i>
                    <h3>Lab Test Results</h3>
                    <p>Check your latest laboratory test findings and reports.</p>
                </a>
                <a href="../billing/billing.php" class="action-card teal">
                    <i class="fas fa-file-invoice-dollar icon"></i>
                    <h3>View Billing</h3>
                    <p>Review your billing statements and payment history.</p>
                </a>
                <a href="../patient/medical_record_print.php" class="action-card green">
                    <i class="fas fa-notes-medical icon"></i>
                    <h3>Medical Records</h3>
                    <p>Access your complete medical history and health records.</p>
                </a>
                <a href="../patient/patient_feedback.php" class="action-card red">
                    <i class="fas fa-comment-dots icon"></i>
                    <h3>Patient Feedback</h3>
                    <p>Share your experience and help us improve our services.</p>
                </a>
            </div>
        </div>

        <!-- Info Layout -->
        <div class="info-layout">
            <!-- Latest Appointment and Vitals -->
            <div class="left-column">
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-alt"></i> Latest Appointment</h3>
                        <a href="../appointment/appointments.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if ($defaults['latest_appointment']['date'] !== '-'): ?>
                        <div class="appointment-layout">
                            <div class="appointment-details">
                                <div class="detail-box">
                                    <span class="label">Date</span>
                                    <span class="value"><?php echo htmlspecialchars($defaults['latest_appointment']['date']); ?></span>
                                </div>
                                <div class="detail-box">
                                    <span class="label">Chief Complaint</span>
                                    <span class="value"><?php echo htmlspecialchars($defaults['latest_appointment']['complaint']); ?></span>
                                </div>
                                <div class="detail-box">
                                    <span class="label">Diagnosis</span>
                                    <span class="value"><?php echo htmlspecialchars($defaults['latest_appointment']['diagnosis']); ?></span>
                                </div>
                                <div class="detail-box">
                                    <span class="label">Treatment</span>
                                    <span class="value"><?php echo htmlspecialchars($defaults['latest_appointment']['treatment']); ?></span>
                                </div>
                            </div>
                            
                            <div class="appointment-vitals">
                                <?php if ($latest_vitals && !empty($latest_vitals['date_recorded'])): ?>
                                    <div class="date-badge">
                                        Recorded <?= htmlspecialchars(date('M d, Y', strtotime($latest_vitals['date_recorded']))) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="vitals-grid">
                                    <div class="vital-box">
                                        <i class="fas fa-ruler-vertical"></i>
                                        <strong>Height</strong>
                                        <div class="vital-value"><?= $latest_vitals ? htmlspecialchars($latest_vitals['ht'] ?? '-') : '-' ?> <?= $latest_vitals && $latest_vitals['ht'] ? 'cm' : '' ?></div>
                                    </div>
                                    <div class="vital-box">
                                        <i class="fas fa-weight"></i>
                                        <strong>Weight</strong>
                                        <div class="vital-value"><?= $latest_vitals ? htmlspecialchars($latest_vitals['wt'] ?? '-') : '-' ?> <?= $latest_vitals && $latest_vitals['wt'] ? 'kg' : '' ?></div>
                                    </div>
                                    <div class="vital-box">
                                        <i class="fas fa-tachometer-alt"></i>
                                        <strong>Blood Pressure</strong>
                                        <div class="vital-value"><?= $latest_vitals ? htmlspecialchars($latest_vitals['bp'] ?? '-') : '-' ?></div>
                                    </div>
                                    <div class="vital-box">
                                        <i class="fas fa-heartbeat"></i>
                                        <strong>Heart Rate</strong>
                                        <div class="vital-value"><?= $latest_vitals ? htmlspecialchars($latest_vitals['hr'] ?? '-') : '-' ?> <?= $latest_vitals && $latest_vitals['hr'] ? 'bpm' : '' ?></div>
                                    </div>
                                    <div class="vital-box">
                                        <i class="fas fa-thermometer-half"></i>
                                        <strong>Temperature</strong>
                                        <div class="vital-value"><?= $latest_vitals ? htmlspecialchars($latest_vitals['temp'] ?? '-') : '-' ?> <?= $latest_vitals && $latest_vitals['temp'] ? '°C' : '' ?></div>
                                    </div>
                                    <div class="vital-box">
                                        <i class="fas fa-lungs"></i>
                                        <strong>Respiratory Rate</strong>
                                        <div class="vital-value"><?= $latest_vitals ? htmlspecialchars($latest_vitals['rr'] ?? '-') : '-' ?> <?= $latest_vitals && $latest_vitals['rr'] ? '/min' : '' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No appointment records found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Notifications -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <a href="../notifications/notifications.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['notifications'])): ?>
                        <div class="scroll-wrapper">
                            <table class="notification-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaults['notifications'] as $notif): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($notif['date']); ?></td>
                                            <td><?php echo htmlspecialchars($notif['description']); ?></td>
                                            <td><span class="status <?php echo $notif['status'] === 'read' ? 'read' : 'unread'; ?>"><?php echo ucfirst($notif['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Log -->
                <div class="card-section">
                    <div class="section-header">
                        <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        <a href="../reports/activity_log.php" class="view-more-btn">
                            <i class="fas fa-chevron-right"></i> View All
                        </a>
                    </div>
                    
                    <?php if (!empty($defaults['activity_log'])): ?>
                        <div class="scroll-wrapper">
                            <ul class="activity-log">
                                <?php foreach ($defaults['activity_log'] as $log): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($log['date']); ?></strong><br>
                                        <?php echo htmlspecialchars($log['activity']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No recent activity to display</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</body>

</html>