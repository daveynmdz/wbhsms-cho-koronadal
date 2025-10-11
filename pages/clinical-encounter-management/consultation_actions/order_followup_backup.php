<?php
// order_followup.php - Follow-up Scheduling Interface
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/employee_login.php');
    exit();
}

// Check if role is authorized
$authorized_roles = ['doctor', 'nurse', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    $_SESSION['snackbar_message'] = 'Access denied. Insufficient permissions.';
    header('Location: ../index.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_role = strtolower($_SESSION['role']);

// Include reusable topbar component
require_once $root_path . '/includes/topbar.php';

// Get encounter ID
$encounter_id = $_GET['encounter_id'] ?? '';
if (!$encounter_id || !is_numeric($encounter_id)) {
    header('Location: ../index.php');
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';
$encounter = null;

// Fetch encounter details
try {
    $stmt = $conn->prepare("
        SELECT e.*, 
               p.first_name as patient_first_name, p.middle_name as patient_middle_name, 
               p.last_name as patient_last_name, p.username as patient_id_display,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as patient_age, p.sex, p.contact_number,
               attending.first_name as doctor_first_name, attending.last_name as doctor_last_name
        FROM clinical_encounters e
        JOIN patients p ON e.patient_id = p.patient_id
        LEFT JOIN employees attending ON e.attending_physician_id = attending.employee_id
        WHERE e.encounter_id = ?
    ");
    $stmt->bind_param('i', $encounter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $encounter = $result->fetch_assoc();
    
    if (!$encounter) {
        header('Location: ../index.php');
        exit();
    }
    
} catch (Exception $e) {
    header('Location: ../index.php');
    exit();
}

// Get existing follow-up appointments for this encounter
$existing_followups = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, 
               scheduled_by.first_name as scheduled_by_first_name, scheduled_by.last_name as scheduled_by_last_name
        FROM appointments a
        LEFT JOIN employees scheduled_by ON a.scheduled_by = scheduled_by.employee_id
        WHERE a.encounter_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->bind_param('i', $encounter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_followups = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for existing followups
}

// Get available doctors for followup assignment
$available_doctors = [];
try {
    $stmt = $conn->prepare("
        SELECT employee_id, first_name, middle_name, last_name, specialization
        FROM employees 
        WHERE role IN ('doctor') AND status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $available_doctors = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for doctor list
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'schedule_followup') {
        try {
            $conn->begin_transaction();
            
            // Get form data
            $followup_type = trim($_POST['followup_type'] ?? '');
            $appointment_date = $_POST['appointment_date'] ?? '';
            $appointment_time = $_POST['appointment_time'] ?? '';
            $assigned_doctor = !empty($_POST['assigned_doctor']) ? (int)$_POST['assigned_doctor'] : null;
            $reason = trim($_POST['reason'] ?? '');
            $priority = trim($_POST['priority'] ?? 'routine');
            $notes = trim($_POST['notes'] ?? '');
            $remind_patient = isset($_POST['remind_patient']) ? 1 : 0;
            $remind_days_before = !empty($_POST['remind_days_before']) ? (int)$_POST['remind_days_before'] : 1;
            
            // Validation
            if (empty($followup_type)) {
                throw new Exception('Follow-up type is required.');
            }
            if (empty($appointment_date)) {
                throw new Exception('Appointment date is required.');
            }
            if (empty($appointment_time)) {
                throw new Exception('Appointment time is required.');
            }
            if (empty($reason)) {
                throw new Exception('Reason for follow-up is required.');
            }
            
            // Validate date is not in the past
            $appointment_datetime = $appointment_date . ' ' . $appointment_time;
            if (strtotime($appointment_datetime) <= time()) {
                throw new Exception('Appointment must be scheduled for a future date and time.');
            }
            
            // Check if slot is available (optional - depends on your booking system)
            if ($assigned_doctor) {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM appointments 
                    WHERE assigned_doctor = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'
                ");
                $stmt->bind_param('iss', $assigned_doctor, $appointment_date, $appointment_time);
                $stmt->execute();
                $result = $stmt->get_result();
                $slot_check = $result->fetch_assoc();
                
                if ($slot_check['count'] > 0) {
                    throw new Exception('Selected doctor is not available at this time. Please choose a different time slot.');
                }
            }
            
            // Generate appointment ID
            $appointment_id = 'APT-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999));
            
            // Insert appointment
            $stmt = $conn->prepare("
                INSERT INTO appointments (
                    appointment_id, patient_id, encounter_id, appointment_type, appointment_date, appointment_time,
                    assigned_doctor, reason, priority, notes, status, scheduled_by, remind_patient,
                    remind_days_before, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, ?, NOW())
            ");
            $stmt->bind_param(
                'siisssisssiii',
                $appointment_id, $encounter['patient_id'], $encounter_id, $followup_type, 
                $appointment_date, $appointment_time, $assigned_doctor, $reason, $priority, 
                $notes, $employee_id, $remind_patient, $remind_days_before
            );
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['snackbar_message'] = "Follow-up appointment scheduled successfully! Appointment ID: " . $appointment_id;
            
            // Redirect back to this page to show updated list
            header("Location: order_followup.php?encounter_id=$encounter_id");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Generate time slots
function generateTimeSlots($start_hour = 8, $end_hour = 17, $interval_minutes = 30) {
    $slots = [];
    for ($hour = $start_hour; $hour < $end_hour; $hour++) {
        for ($minute = 0; $minute < 60; $minute += $interval_minutes) {
            $time = sprintf('%02d:%02d', $hour, $minute);
            $display_time = date('g:i A', strtotime($time));
            $slots[] = ['value' => $time, 'display' => $display_time];
        }
    }
    return $slots;
}

$time_slots = generateTimeSlots();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Schedule Follow-up - <?= htmlspecialchars($encounter['patient_first_name'] . ' ' . $encounter['patient_last_name']) ?> | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .encounter-info-card {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin-bottom: 2rem;
        }

        .encounter-info-header {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .encounter-info-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            font-size: 0.9rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 600;
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        .existing-followups-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #2196f3;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .followup-card {
            background: #f3f8ff;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .followup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .followup-type {
            font-weight: 600;
            color: #1976d2;
            font-size: 1.1rem;
        }

        .followup-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-scheduled {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-completed {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .followup-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-sections {
            display: grid;
            gap: 2rem;
        }

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #1976d2;
        }

        .form-grid {
            display: grid;
            gap: 1rem;
        }

        .form-grid.two-column {
            grid-template-columns: 1fr 1fr;
        }

        .form-grid.three-column {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .priority-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
        }

        .priority-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border: 2px solid #e9ecef;
        }

        .priority-option:hover {
            background: #f8f9fa;
            border-color: #1976d2;
        }

        .priority-option input[type="radio"]:checked + label {
            color: #1976d2;
            font-weight: 600;
        }

        .priority-routine {
            border-left: 4px solid #28a745;
        }

        .priority-urgent {
            border-left: 4px solid #ffc107;
        }

        .priority-emergency {
            border-left: 4px solid #dc3545;
        }

        .followup-type-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .followup-type-option {
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .followup-type-option:hover {
            border-color: #1976d2;
            background: #f3f8ff;
        }

        .followup-type-option.selected {
            border-color: #1976d2;
            background: #e3f2fd;
        }

        .type-icon {
            font-size: 2rem;
            color: #1976d2;
            margin-bottom: 0.5rem;
        }

        .type-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .type-description {
            font-size: 0.85rem;
            color: #666;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b2b7;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1976d2, #42a5f5);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }

        .reminder-section {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin: 1rem 0;
        }

        .reminder-title {
            font-weight: 600;
            color: #856404;
            margin-bottom: 0.5rem;
        }

        .reminder-content {
            font-size: 0.9rem;
            color: #856404;
        }

        @media (max-width: 768px) {
            .encounter-info-details {
                grid-template-columns: 1fr;
            }

            .followup-details {
                grid-template-columns: 1fr;
            }

            .form-grid.two-column,
            .form-grid.three-column {
                grid-template-columns: 1fr;
            }

            .followup-type-options {
                grid-template-columns: 1fr;
            }

            .priority-options {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php 
    // Render snackbar notification system
    renderSnackbar();
    
    // Render topbar
    renderTopbar([
        'title' => 'Schedule Follow-up',
        'back_url' => "../view_consultation.php?id=$encounter_id",
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <?php 
        // Render back button
        renderBackButton([
            'back_url' => "../view_consultation.php?id=$encounter_id",
            'button_text' => '← Back to Consultation'
        ]);
        ?>

        <div class="profile-wrapper">
            <!-- Reminders Box -->
            <div class="reminders-box">
                <strong>Follow-up Scheduling Guidelines:</strong>
                <ul>
                    <li>Schedule follow-ups based on patient's condition and treatment plan.</li>
                    <li>Consider patient availability and transportation constraints.</li>
                    <li>Set appropriate reminder times to reduce no-shows.</li>
                    <li>Assign specific doctors when continuity of care is important.</li>
                    <li>Document clear reasons for follow-up visits.</li>
                    <li>Review previous encounter notes to ensure proper follow-up care.</li>
                </ul>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Encounter Information -->
            <div class="encounter-info-card">
                <div class="encounter-info-header">
                    <i class="fas fa-user-check"></i> Patient & Encounter Information
                </div>
                <div class="encounter-info-details">
                    <div class="info-item">
                        <div class="info-label">Patient Name</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['patient_first_name'] . ' ' . 
                            ($encounter['patient_middle_name'] ? $encounter['patient_middle_name'] . ' ' : '') . 
                            $encounter['patient_last_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Patient ID</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['patient_id_display']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Age / Sex</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['patient_age']) ?> years / <?= htmlspecialchars($encounter['sex']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contact Number</div>
                        <div class="info-value"><?= !empty($encounter['contact_number']) ? htmlspecialchars($encounter['contact_number']) : 'Not available' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Encounter Date</div>
                        <div class="info-value"><?= date('M j, Y g:i A', strtotime($encounter['created_at'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Attending Physician</div>
                        <div class="info-value"><?= $encounter['doctor_first_name'] ? 'Dr. ' . htmlspecialchars($encounter['doctor_first_name'] . ' ' . $encounter['doctor_last_name']) : 'Not assigned' ?></div>
                    </div>
                </div>
            </div>

            <!-- Existing Follow-ups -->
            <div class="existing-followups-section">
                <h3 class="section-title">
                    <i class="fas fa-calendar-alt"></i> Scheduled Follow-ups (<?= count($existing_followups) ?>)
                </h3>
                <?php if (!empty($existing_followups)): ?>
                    <?php foreach ($existing_followups as $followup): ?>
                        <div class="followup-card">
                            <div class="followup-header">
                                <div class="followup-type"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $followup['appointment_type']))) ?></div>
                                <div class="followup-status status-<?= htmlspecialchars($followup['status']) ?>">
                                    <?= htmlspecialchars(ucwords($followup['status'])) ?>
                                </div>
                            </div>
                            <div class="followup-details">
                                <div class="info-item">
                                    <div class="info-label">Appointment Date</div>
                                    <div class="info-value"><?= date('M j, Y', strtotime($followup['appointment_date'])) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Time</div>
                                    <div class="info-value"><?= date('g:i A', strtotime($followup['appointment_time'])) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Priority</div>
                                    <div class="info-value"><?= htmlspecialchars(ucwords($followup['priority'])) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Assigned Doctor</div>
                                    <div class="info-value">
                                        <?php if ($followup['assigned_doctor']): ?>
                                            <?php 
                                            $assigned_doc = array_filter($available_doctors, function($doc) use ($followup) {
                                                return $doc['employee_id'] == $followup['assigned_doctor'];
                                            });
                                            $assigned_doc = reset($assigned_doc);
                                            ?>
                                            <?= $assigned_doc ? 'Dr. ' . htmlspecialchars($assigned_doc['first_name'] . ' ' . $assigned_doc['last_name']) : 'Unknown' ?>
                                        <?php else: ?>
                                            Any available doctor
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Scheduled By</div>
                                    <div class="info-value"><?= htmlspecialchars($followup['scheduled_by_first_name'] . ' ' . $followup['scheduled_by_last_name']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Appointment ID</div>
                                    <div class="info-value"><?= htmlspecialchars($followup['appointment_id']) ?></div>
                                </div>
                            </div>
                            <?php if (!empty($followup['reason'])): ?>
                            <div class="info-item" style="margin-top: 1rem;">
                                <div class="info-label">Reason</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($followup['reason'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($followup['notes'])): ?>
                            <div class="info-item" style="margin-top: 1rem;">
                                <div class="info-label">Notes</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($followup['notes'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times fa-2x"></i>
                        <p>No follow-up appointments have been scheduled for this consultation yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Schedule New Follow-up Form -->
            <form method="POST" class="followup-form">
                <input type="hidden" name="action" value="schedule_followup">

                <div class="form-sections">
                    <!-- Follow-up Type Selection -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-plus"></i> Schedule New Follow-up
                        </h3>
                        
                        <div class="form-group">
                            <label>Follow-up Type <span class="required">*</span></label>
                            <div class="followup-type-options">
                                <div class="followup-type-option" data-type="consultation">
                                    <div class="type-icon"><i class="fas fa-user-md"></i></div>
                                    <div class="type-title">Medical Consultation</div>
                                    <div class="type-description">Regular check-up or follow-up consultation</div>
                                </div>
                                <div class="followup-type-option" data-type="laboratory">
                                    <div class="type-icon"><i class="fas fa-flask"></i></div>
                                    <div class="type-title">Laboratory Results</div>
                                    <div class="type-description">Review lab test results</div>
                                </div>
                                <div class="followup-type-option" data-type="procedure">
                                    <div class="type-icon"><i class="fas fa-procedures"></i></div>
                                    <div class="type-title">Procedure/Treatment</div>
                                    <div class="type-description">Specific medical procedure or treatment</div>
                                </div>
                                <div class="followup-type-option" data-type="monitoring">
                                    <div class="type-icon"><i class="fas fa-heartbeat"></i></div>
                                    <div class="type-title">Condition Monitoring</div>
                                    <div class="type-description">Monitor chronic condition or treatment progress</div>
                                </div>
                                <div class="followup-type-option" data-type="vaccination">
                                    <div class="type-icon"><i class="fas fa-syringe"></i></div>
                                    <div class="type-title">Vaccination</div>
                                    <div class="type-description">Scheduled vaccination or immunization</div>
                                </div>
                                <div class="followup-type-option" data-type="referral_followup">
                                    <div class="type-icon"><i class="fas fa-share"></i></div>
                                    <div class="type-title">Referral Follow-up</div>
                                    <div class="type-description">Follow-up after specialist referral</div>
                                </div>
                            </div>
                            <input type="hidden" name="followup_type" id="followup_type" required>
                        </div>
                    </div>

                    <!-- Appointment Details -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clock"></i> Appointment Details
                        </h3>
                        
                        <div class="form-grid two-column">
                            <div class="form-group">
                                <label for="appointment_date">Appointment Date <span class="required">*</span></label>
                                <input type="date" id="appointment_date" name="appointment_date" required
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                            <div class="form-group">
                                <label for="appointment_time">Appointment Time <span class="required">*</span></label>
                                <select id="appointment_time" name="appointment_time" required>
                                    <option value="">Select time...</option>
                                    <?php foreach ($time_slots as $slot): ?>
                                        <option value="<?= htmlspecialchars($slot['value']) ?>"><?= htmlspecialchars($slot['display']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="assigned_doctor">Assigned Doctor</label>
                            <select id="assigned_doctor" name="assigned_doctor">
                                <option value="">Any available doctor</option>
                                <?php foreach ($available_doctors as $doctor): ?>
                                    <option value="<?= $doctor['employee_id'] ?>">
                                        Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>
                                        <?php if ($doctor['specialization']): ?>
                                            (<?= htmlspecialchars($doctor['specialization']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Priority Level <span class="required">*</span></label>
                            <div class="priority-options">
                                <div class="priority-option priority-routine">
                                    <input type="radio" name="priority" value="routine" id="priority-routine" checked>
                                    <label for="priority-routine">Routine</label>
                                </div>
                                <div class="priority-option priority-urgent">
                                    <input type="radio" name="priority" value="urgent" id="priority-urgent">
                                    <label for="priority-urgent">Urgent</label>
                                </div>
                                <div class="priority-option priority-emergency">
                                    <input type="radio" name="priority" value="emergency" id="priority-emergency">
                                    <label for="priority-emergency">Emergency</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clinical Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-list"></i> Clinical Information
                        </h3>
                        
                        <div class="form-group">
                            <label for="reason">Reason for Follow-up <span class="required">*</span></label>
                            <textarea id="reason" name="reason" required
                                      placeholder="Describe the reason for this follow-up appointment..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea id="notes" name="notes"
                                      placeholder="Any additional instructions or notes for the appointment..."></textarea>
                        </div>
                    </div>

                    <!-- Patient Reminders -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-bell"></i> Patient Reminders
                        </h3>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="remind_patient" name="remind_patient" checked>
                            <label for="remind_patient">Send reminder to patient</label>
                        </div>

                        <div class="form-group" id="reminder-options">
                            <label for="remind_days_before">Remind patient (days before appointment)</label>
                            <select id="remind_days_before" name="remind_days_before">
                                <option value="1" selected>1 day before</option>
                                <option value="2">2 days before</option>
                                <option value="3">3 days before</option>
                                <option value="7">1 week before</option>
                            </select>
                        </div>

                        <div class="reminder-section">
                            <div class="reminder-title"><i class="fas fa-info-circle"></i> Reminder Information</div>
                            <div class="reminder-content">
                                If patient contact information is available, reminders will be sent via SMS or phone call.
                                Ensure patient contact details are up to date for effective communication.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../view_consultation.php?id=<?= $encounter_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Schedule Follow-up
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle follow-up type selection
            const typeOptions = document.querySelectorAll('.followup-type-option');
            const typeInput = document.getElementById('followup_type');
            
            typeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    typeOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Set hidden input value
                    typeInput.value = this.dataset.type;
                });
            });

            // Handle remind patient checkbox
            const remindCheckbox = document.getElementById('remind_patient');
            const reminderOptions = document.getElementById('reminder-options');
            
            function toggleReminderOptions() {
                reminderOptions.style.display = remindCheckbox.checked ? 'block' : 'none';
            }
            
            remindCheckbox.addEventListener('change', toggleReminderOptions);
            toggleReminderOptions(); // Initial state

            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const followupType = document.getElementById('followup_type').value;
                    const appointmentDate = document.getElementById('appointment_date').value;
                    const appointmentTime = document.getElementById('appointment_time').value;
                    const reason = document.getElementById('reason').value.trim();
                    
                    if (!followupType) {
                        e.preventDefault();
                        alert('Please select a follow-up type.');
                        return false;
                    }
                    
                    if (!appointmentDate) {
                        e.preventDefault();
                        alert('Please select an appointment date.');
                        document.getElementById('appointment_date').focus();
                        return false;
                    }
                    
                    if (!appointmentTime) {
                        e.preventDefault();
                        alert('Please select an appointment time.');
                        document.getElementById('appointment_time').focus();
                        return false;
                    }
                    
                    if (!reason) {
                        e.preventDefault();
                        alert('Please enter the reason for follow-up.');
                        document.getElementById('reason').focus();
                        return false;
                    }
                    
                    // Validate that appointment is in the future
                    const appointmentDateTime = new Date(appointmentDate + ' ' + appointmentTime);
                    const now = new Date();
                    
                    if (appointmentDateTime <= now) {
                        e.preventDefault();
                        alert('Appointment must be scheduled for a future date and time.');
                        return false;
                    }
                });
            }

            // Set minimum date to tomorrow
            const dateInput = document.getElementById('appointment_date');
            if (dateInput) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                dateInput.min = tomorrow.toISOString().split('T')[0];
            }
        });
    </script>
</body>

</html>