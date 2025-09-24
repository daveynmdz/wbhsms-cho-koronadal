<?php
// dashboard_patient.php - moved from dashboard folder to patient folder
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include patient session configuration - Use absolute path resolution
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, bounce to login
if (!is_patient_logged_in()) {
    header('Location: auth/patient_login.php'); // correct path to auth folder
    exit();
}

// DB
require_once $root_path . '/config/db.php'; // adjust relative path if needed
$patient_id = $_SESSION['patient_id'];

// -------------------- Data bootstrap (from patientHomepage.php) --------------------
$defaults = [
    'name' => 'Patient',
    'patient_number' => '-',
    'latest_appointment' => [
        'status' => 'none',
        'date' => '',
        'time' => '',
        'description' => 'No upcoming appointments'
    ],
    'latest_prescription' => [
        'status' => 'none',
        'date' => '',
        'doctor' => '',
        'description' => 'No active prescriptions'
    ]
];

// Load patient info
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.patient_id,
            p.username as patient_number,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.suffix,
            p.date_of_birth,
            p.sex,
            p.civil_status,
            p.contact_num,
            b.barangay_name as barangay,
            p.address,
            p.email
        FROM 
            patients p
        LEFT JOIN 
            barangay b ON p.barangay_id = b.barangay_id
        WHERE 
            p.patient_id = ?
    ");

    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        // Build full name
        $nameParts = [];
        if (!empty($patient['first_name'])) $nameParts[] = $patient['first_name'];
        if (!empty($patient['middle_name'])) $nameParts[] = $patient['middle_name'];
        if (!empty($patient['last_name'])) $nameParts[] = $patient['last_name'];
        if (!empty($patient['suffix'])) $nameParts[] = $patient['suffix'];

        $fullName = implode(' ', $nameParts);
        $defaults['name'] = $fullName;
        $defaults['patient_number'] = $patient['patient_number'];

        // Also set session variables to ensure sidebar gets the data
        $_SESSION['patient_name'] = $fullName;
        $_SESSION['patient_number'] = $patient['patient_number'];
    }
} catch (PDOException $e) {
    // Log error but don't expose to user
    error_log('Patient dashboard error: ' . $e->getMessage());
}

// Load latest appointment
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.appointment_id,
            a.appointment_date,
            a.appointment_time,
            a.reason,
            a.status,
            e.first_name AS doctor_first_name,
            e.last_name AS doctor_last_name
        FROM 
            appointments a
        LEFT JOIN 
            employees e ON a.doctor_id = e.employee_id
        WHERE 
            a.patient_id = ? AND 
            a.status IN ('pending', 'confirmed', 'in-progress') AND
            (a.appointment_date > CURRENT_DATE OR 
             (a.appointment_date = CURRENT_DATE AND a.appointment_time >= CURRENT_TIME))
        ORDER BY 
            a.appointment_date ASC, 
            a.appointment_time ASC
        LIMIT 1
    ");

    $stmt->execute([$patient_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment) {
        // Format date
        $appointmentDate = new DateTime($appointment['appointment_date']);
        $formattedDate = $appointmentDate->format('F j, Y');

        // Format time
        $appointmentTime = new DateTime($appointment['appointment_time']);
        $formattedTime = $appointmentTime->format('g:i A');

        $doctorName = '';
        if (!empty($appointment['doctor_first_name']) && !empty($appointment['doctor_last_name'])) {
            $doctorName = "Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']}";
        }

        $defaults['latest_appointment'] = [
            'id' => $appointment['appointment_id'],
            'status' => $appointment['status'],
            'date' => $formattedDate,
            'time' => $formattedTime,
            'doctor' => $doctorName,
            'reason' => $appointment['reason'],
            'description' => $appointment['reason']
        ];
    }
} catch (PDOException $e) {
    error_log('Patient dashboard - appointment error: ' . $e->getMessage());
}

// Load latest prescription
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.prescription_id,
            p.date_prescribed,
            p.status,
            p.notes,
            e.first_name AS doctor_first_name,
            e.last_name AS doctor_last_name
        FROM 
            prescriptions p
        LEFT JOIN 
            employees e ON p.doctor_id = e.employee_id
        WHERE 
            p.patient_id = ? AND 
            p.status IN ('active', 'pending', 'ready')
        ORDER BY 
            p.date_prescribed DESC
        LIMIT 1
    ");

    $stmt->execute([$patient_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($prescription) {
        // Format date
        $prescriptionDate = new DateTime($prescription['date_prescribed']);
        $formattedDate = $prescriptionDate->format('F j, Y');

        $doctorName = '';
        if (!empty($prescription['doctor_first_name']) && !empty($prescription['doctor_last_name'])) {
            $doctorName = "Dr. {$prescription['doctor_first_name']} {$prescription['doctor_last_name']}";
        }

        $defaults['latest_prescription'] = [
            'id' => $prescription['prescription_id'],
            'status' => $prescription['status'],
            'date' => $formattedDate,
            'doctor' => $doctorName,
            'notes' => $prescription['notes'],
            'description' => "Prescription: " . ($prescription['notes'] ?: 'No details available')
        ];
    }
} catch (PDOException $e) {
    error_log('Patient dashboard - prescription error: ' . $e->getMessage());
}

// Set active page for sidebar highlighting
$activePage = 'dashboard';

// Ensure defaults array is properly set for sidebar
if (!isset($defaults)) {
    $defaults = ['name' => 'Patient', 'patient_number' => ''];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">

    <style>
        .content-wrapper {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
            }
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .dashboard-title {
            font-size: 1.8rem;
            color: #0077b6;
            margin: 0;
        }

        .dashboard-actions {
            display: flex;
            gap: 1rem;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border-left: 4px solid #0077b6;
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

        .info-card h2 i {
            color: #0077b6;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.2rem;
            color: #0077b6;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-status {
            padding: 0.3rem 0.6rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-confirmed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-active {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background-color: #e5e7eb;
            color: #374151;
        }

        .card-content {
            margin-bottom: 1rem;
        }

        .card-detail {
            display: flex;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .detail-label {
            font-weight: 600;
            color: #6b7280;
            width: 70px;
            flex-shrink: 0;
        }

        .detail-value {
            color: #1f2937;
        }

        .card-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
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
        }

        .btn-primary {
            background-color: #0077b6;
            color: white;
        }

        .btn-primary:hover {
            background-color: #023e8a;
        }

        .btn-secondary {
            background-color: #f3f4f6;
            color: #1f2937;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
        }

        .section-divider {
            margin: 2.5rem 0;
            border: none;
            border-top: 1px solid #e5e7eb;
        }

        .quick-actions {
            margin-top: 2rem;
        }

        .actions-title {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 1.5rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 160px;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #0077b6;
            transition: transform 0.2s;
        }

        .action-card:hover .action-icon {
            transform: scale(1.1);
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .action-description {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-top: 80px;

            }

            .dashboard-actions {
                width: 100%;
                justify-content: space-between;
            }

            .action-card {
                min-height: 140px;
            }
        }

        /* Welcome message animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-message {
            animation: fadeInUp 0.8s ease-out;
        }

        /* Card entry animation */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
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

        /* Accessibility improvements */
        .visually-hidden {
            border: 0;
            clip: rect(0 0 0 0);
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            width: 1px;
        }

        /* Tooltip */
        .tooltip {
            position: relative;
        }

        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            white-space: nowrap;
            z-index: 10;
        }
    </style>
</head>

<body>
    <?php include '../../includes/sidebar_patient.php'; ?>

    <main class="content-wrapper">
        <section class="dashboard-header">
            <div class="welcome-message">
                <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($defaults['name']); ?></h1>
                <p>Here's what's happening with your health today.</p>
            </div>

            <div class="dashboard-actions">
                <a href="appointment/appointments.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="profile/id_card.php" class="btn btn-secondary">
                    <i class="fas fa-id-card"></i> View ID Card
                </a>
            </div>
        </section>

        <section class="info-card">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
            <div class="notification-list">
                <p>You have no new notifications at this time.</p>
                <!-- Notifications will be dynamically loaded here -->
            </div>
        </section>

        <section class="card-grid">
            <!-- Appointment Card -->
            <div class="card animated-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-calendar-check"></i> Upcoming Appointment
                    </h2>
                    <?php if ($defaults['latest_appointment']['status'] !== 'none'): ?>
                        <span class="card-status status-<?php echo htmlspecialchars($defaults['latest_appointment']['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($defaults['latest_appointment']['status'])); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($defaults['latest_appointment']['status'] !== 'none'): ?>
                    <div class="card-content">
                        <div class="card-detail">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($defaults['latest_appointment']['date']); ?></span>
                        </div>
                        <div class="card-detail">
                            <span class="detail-label">Time:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($defaults['latest_appointment']['time']); ?></span>
                        </div>
                        <?php if (!empty($defaults['latest_appointment']['doctor'])): ?>
                            <div class="card-detail">
                                <span class="detail-label">Doctor:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($defaults['latest_appointment']['doctor']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="card-detail">
                            <span class="detail-label">Reason:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($defaults['latest_appointment']['reason']); ?></span>
                        </div>
                    </div>

                    <div class="card-actions">
                        <a href="appointment/appointments.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card-content">
                        <p>You have no upcoming appointments.</p>
                    </div>

                    <div class="card-actions">
                        <a href="appointment/appointments.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Prescription Card -->
            <div class="card animated-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-prescription-bottle-alt"></i> Active Prescription
                    </h2>
                    <?php if ($defaults['latest_prescription']['status'] !== 'none'): ?>
                        <span class="card-status status-<?php echo htmlspecialchars($defaults['latest_prescription']['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($defaults['latest_prescription']['status'])); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($defaults['latest_prescription']['status'] !== 'none'): ?>
                    <div class="card-content">
                        <div class="card-detail">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($defaults['latest_prescription']['date']); ?></span>
                        </div>
                        <?php if (!empty($defaults['latest_prescription']['doctor'])): ?>
                            <div class="card-detail">
                                <span class="detail-label">Doctor:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($defaults['latest_prescription']['doctor']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($defaults['latest_prescription']['notes'])): ?>
                            <div class="card-detail">
                                <span class="detail-label">Notes:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($defaults['latest_prescription']['notes']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-actions">
                        <a href="appointment/appointments.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card-content">
                        <p>You have no active prescriptions.</p>
                    </div>

                    <div class="card-actions">
                        <a href="appointment/appointments.php" class="btn btn-secondary">
                            <i class="fas fa-history"></i> View History
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <hr class="section-divider">

        <section class="quick-actions">
            <h2 class="actions-title">Quick Actions</h2>

            <div class="actions-grid">
                <a href="appointment/appointments.php" class="action-card">
                    <i class="fas fa-calendar-plus action-icon"></i>
                    <h3 class="action-title">Book Appointment</h3>
                    <p class="action-description">Schedule a visit with a healthcare provider</p>
                </a>

                <a href="profile/medical_record_print.php" class="action-card">
                    <i class="fas fa-file-medical action-icon"></i>
                    <h3 class="action-title">Medical Records</h3>
                    <p class="action-description">View and print your medical history</p>
                </a>

                <a href="profile/profile.php" class="action-card">
                    <i class="fas fa-user-edit action-icon"></i>
                    <h3 class="action-title">Update Profile</h3>
                    <p class="action-description">Keep your information up to date</p>
                </a>

                <a href="profile/id_card.php" class="action-card">
                    <i class="fas fa-id-card action-icon"></i>
                    <h3 class="action-title">Patient ID</h3>
                    <p class="action-description">View and print your patient ID card</p>
                </a>
            </div>
        </section>
    </main>

    <script>
        // Simple animation for the cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.animated-card');
            cards.forEach(card => {
                card.style.opacity = '1';
            });
        });
    </script>
</body>

</html>