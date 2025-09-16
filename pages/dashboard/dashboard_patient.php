<?php
// dashboard_patient.php
session_start();

// If user is not logged in, bounce to login
if (!isset($_SESSION['patient_id'])) {
    header('Location: patient_login.php'); // make sure this matches your actual filename/path
    exit();
}

// DB
require_once '../../config/db.php'; // adjust relative path if needed
$patient_id = $_SESSION['patient_id'];

// Tell the sidebar which menu item to highlight
$activePage = 'dashboard';

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
$stmt = $pdo->prepare('SELECT last_name, first_name, middle_name, suffix, username FROM patients WHERE id = ?');
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
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        /* Optional: small layout wrapper for content next to sidebar */
        .content-wrapper {
            display: block;
            margin-left: var(--sidebar-width, 260px);
            /* if your sidebar uses fixed width */
            padding: 1.25rem;
        }

        @media (max-width: 960px) {
            .content-wrapper {
                margin-left: 0;
            }
        }

        .page-title {
            margin: 0 0 1rem;
        }

        /* reuse existing .card-section, .section-header, tables etc from your CSS */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
            padding: 2rem 2.5rem;
            text-align: center;
            min-width: 300px;
            max-width: 90vw;
        }

        .modal-content h2 {
            margin-top: 0;
            color: #d9534f;
        }

        .modal-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-danger {
            background: #d9534f;
            color: #fff;
        }

        .btn-danger:hover {
            background: #c9302c;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }
    </style>
</head>

<body>

    <?php
    $activePage = 'dashboard'; // highlight menu
    include 'sidebar_patient.php';
    ?>
    <section class="homepage">
        <h1 class="page-title">Welcome, <?= htmlspecialchars($defaults['name']) ?>!</h1>

        <div class="card-container" style="margin-bottom: 1rem;">
            <h3>What would you like to do?</h3>
            <div class="card-button-container">
                <a href="#" class="card-button blue-card">
                    <i class="fas fa-calendar-check icon"></i>
                    <h3>Set an Appointment</h3>
                    <p>Schedule a consultation or check-up.</p>
                </a>
                <a href="#" class="card-button purple-card">
                    <i class="fas fa-prescription-bottle-alt icon"></i>
                    <h3>View Prescription</h3>
                    <p>Access your prescribed medications.</p>
                </a>
                <a href="#" class="card-button orange-card">
                    <i class="fas fa-vials icon"></i>
                    <h3>View Lab Test Results</h3>
                    <p>Check your latest lab test findings.</p>
                </a>
                <a href="#" class="card-button teal-card">
                    <i class="fas fa-file-invoice-dollar icon"></i>
                    <h3>View Billing</h3>
                    <p>Review your billing and payments.</p>
                </a>
            </div>
        </div>
    </section>

</body>

</html>