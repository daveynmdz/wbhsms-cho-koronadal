<?php
// view_consultation.php - Read-only View of Clinical Encounters
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check if role is authorized
$authorized_roles = ['doctor', 'nurse', 'admin', 'records_officer'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: ../dashboard.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_role = strtolower($_SESSION['role']);

// Include reusable topbar component
require_once $root_path . '/includes/topbar.php';

// Get encounter ID
$encounter_id = $_GET['id'] ?? '';
if (!$encounter_id || !is_numeric($encounter_id)) {
    header('Location: index.php');
    exit();
}

// Fetch encounter details
$encounter = null;
$patient = null;
$vitals = null;
$prescriptions = [];
$lab_tests = [];

try {
    // Fetch encounter with patient and doctor info
    $stmt = $conn->prepare("
        SELECT e.*, 
               p.first_name as patient_first_name, p.middle_name as patient_middle_name, 
               p.last_name as patient_last_name, p.username as patient_id_display,
               p.date_of_birth, p.sex, p.contact_number, p.email, p.address,
               b.barangay_name,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as patient_age,
               d.first_name as doctor_first_name, d.last_name as doctor_last_name,
               creator.first_name as creator_first_name, creator.last_name as creator_last_name
        FROM clinical_encounters e
        JOIN patients p ON e.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN employees d ON e.doctor_id = d.employee_id
        LEFT JOIN employees creator ON e.created_by = creator.employee_id
        WHERE e.encounter_id = ?
    ");
    $stmt->bind_param('i', $encounter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $encounter = $result->fetch_assoc();
    
    if (!$encounter) {
        header('Location: index.php');
        exit();
    }
    
    // Fetch vitals if available
    if ($encounter['vitals_id']) {
        $stmt = $conn->prepare("
            SELECT v.*, recorder.first_name as recorder_first_name, recorder.last_name as recorder_last_name
            FROM vitals v
            LEFT JOIN employees recorder ON v.recorded_by = recorder.employee_id
            WHERE v.vitals_id = ?
        ");
        $stmt->bind_param('i', $encounter['vitals_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $vitals = $result->fetch_assoc();
    }
    
    // Fetch prescriptions
    $stmt = $conn->prepare("
        SELECT p.*, prescriber.first_name as prescriber_first_name, prescriber.last_name as prescriber_last_name
        FROM prescriptions p
        LEFT JOIN employees prescriber ON p.prescribed_by = prescriber.employee_id
        WHERE p.encounter_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->bind_param('i', $encounter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $prescriptions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Fetch lab tests
    $stmt = $conn->prepare("
        SELECT l.*, orderer.first_name as orderer_first_name, orderer.last_name as orderer_last_name
        FROM lab_tests l
        LEFT JOIN employees orderer ON l.ordered_by = orderer.employee_id
        WHERE l.encounter_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->bind_param('i', $encounter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lab_tests = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>View Consultation - <?= htmlspecialchars($encounter['patient_first_name'] . ' ' . $encounter['patient_last_name']) ?> | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .encounter-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .encounter-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .encounter-title h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .encounter-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.2);
            color: #d4edda;
        }

        .status-in_progress {
            background: rgba(255, 193, 7, 0.2);
            color: #fff3cd;
        }

        .status-follow_up_required {
            background: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
        }

        .encounter-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            font-size: 0.9rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.9;
        }

        .patient-info-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
        }

        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .patient-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #28a745;
        }

        .patient-id {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: #6c757d;
            font-weight: 600;
        }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .content-sections {
            display: grid;
            gap: 2rem;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0077b6;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-grid {
            display: grid;
            gap: 1.5rem;
        }

        .content-grid.two-column {
            grid-template-columns: 1fr 1fr;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .field-group.full-width {
            grid-column: 1 / -1;
        }

        .field-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }

        .field-value {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            color: #333;
            line-height: 1.5;
            min-height: 60px;
        }

        .field-value.empty {
            color: #6c757d;
            font-style: italic;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .vital-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e9ecef;
        }

        .vital-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0077b6;
            margin-bottom: 0.25rem;
        }

        .vital-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vital-unit {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .prescriptions-section,
        .lab-tests-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6610f2;
        }

        .items-grid {
            display: grid;
            gap: 1rem;
        }

        .item-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .item-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .item-title {
            font-weight: 600;
            color: #0077b6;
        }

        .item-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 0.75rem 1.5rem;
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
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #17a2b8;
        }

        .quick-actions h3 {
            margin-bottom: 1rem;
            color: #17a2b8;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .encounter-title {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
                text-align: center;
            }

            .patient-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
                text-align: center;
            }

            .encounter-meta {
                grid-template-columns: 1fr;
            }

            .patient-details {
                grid-template-columns: 1fr;
            }

            .content-grid.two-column {
                grid-template-columns: 1fr;
            }

            .vitals-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }

            .action-buttons {
                flex-direction: column;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
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
        'title' => 'View Consultation',
        'back_url' => 'index.php',
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <?php 
        // Render back button
        renderBackButton([
            'back_url' => 'index.php',
            'button_text' => '← Back to Encounters'
        ]);
        ?>

        <div class="profile-wrapper">
            <!-- Encounter Header -->
            <div class="encounter-header">
                <div class="encounter-title">
                    <h1><i class="fas fa-stethoscope"></i> Clinical Consultation</h1>
                    <div class="encounter-status status-<?= htmlspecialchars($encounter['status']) ?>">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $encounter['status']))) ?>
                    </div>
                </div>
                <div class="encounter-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span>Created: <?= date('M j, Y g:i A', strtotime($encounter['created_at'])) ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user-md"></i>
                        <span>Doctor: <?= $encounter['doctor_first_name'] ? 
                            'Dr. ' . htmlspecialchars($encounter['doctor_first_name'] . ' ' . $encounter['doctor_last_name']) : 
                            'Not assigned' ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Created by: <?= htmlspecialchars($encounter['creator_first_name'] . ' ' . $encounter['creator_last_name']) ?></span>
                    </div>
                    <?php if ($encounter['updated_at'] != $encounter['created_at']): ?>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span>Last updated: <?= date('M j, Y g:i A', strtotime($encounter['updated_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Patient Information -->
            <div class="patient-info-card">
                <div class="patient-header">
                    <div class="patient-name">
                        <?= htmlspecialchars($encounter['patient_first_name'] . ' ' . 
                            ($encounter['patient_middle_name'] ? $encounter['patient_middle_name'] . ' ' : '') . 
                            $encounter['patient_last_name']) ?>
                    </div>
                    <div class="patient-id"><?= htmlspecialchars($encounter['patient_id_display']) ?></div>
                </div>
                <div class="patient-details">
                    <div class="detail-item">
                        <div class="detail-label">Age / Sex</div>
                        <div class="detail-value"><?= htmlspecialchars($encounter['patient_age']) ?> years / <?= htmlspecialchars($encounter['sex']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date of Birth</div>
                        <div class="detail-value"><?= date('M j, Y', strtotime($encounter['date_of_birth'])) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Contact Number</div>
                        <div class="detail-value"><?= htmlspecialchars($encounter['contact_number'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Barangay</div>
                        <div class="detail-value"><?= htmlspecialchars($encounter['barangay_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Address</div>
                        <div class="detail-value"><?= htmlspecialchars($encounter['address'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?= htmlspecialchars($encounter['email'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <?php if (in_array($employee_role, ['doctor', 'admin']) || 
                      ($encounter['status'] == 'in_progress' && $employee_role == 'nurse')): ?>
            <div class="quick-actions">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="quick-actions-grid">
                    <a href="edit_consultation.php?id=<?= $encounter['encounter_id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Consultation
                    </a>
                    <a href="consultation_actions/issue_prescription.php?encounter_id=<?= $encounter['encounter_id'] ?>" class="btn btn-success">
                        <i class="fas fa-pills"></i> Issue Prescription
                    </a>
                    <a href="consultation_actions/order_lab_test.php?encounter_id=<?= $encounter['encounter_id'] ?>" class="btn btn-warning">
                        <i class="fas fa-vial"></i> Order Lab Test
                    </a>
                    <a href="consultation_actions/order_followup.php?encounter_id=<?= $encounter['encounter_id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-calendar-plus"></i> Schedule Follow-up
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-sections">
                <!-- Chief Complaint & History -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-list"></i> Chief Complaint & History
                        </h3>
                    </div>
                    <div class="content-grid">
                        <div class="field-group full-width">
                            <div class="field-label">Chief Complaint</div>
                            <div class="field-value <?= empty($encounter['chief_complaint']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['chief_complaint']) ? nl2br(htmlspecialchars($encounter['chief_complaint'])) : 'No chief complaint recorded' ?>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <div class="field-label">History of Present Illness</div>
                            <div class="field-value <?= empty($encounter['history_present_illness']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['history_present_illness']) ? nl2br(htmlspecialchars($encounter['history_present_illness'])) : 'No history of present illness recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Past Medical History</div>
                            <div class="field-value <?= empty($encounter['past_medical_history']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['past_medical_history']) ? nl2br(htmlspecialchars($encounter['past_medical_history'])) : 'None recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Current Medications</div>
                            <div class="field-value <?= empty($encounter['medications']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['medications']) ? nl2br(htmlspecialchars($encounter['medications'])) : 'None recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Allergies</div>
                            <div class="field-value <?= empty($encounter['allergies']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['allergies']) ? nl2br(htmlspecialchars($encounter['allergies'])) : 'None recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Social History</div>
                            <div class="field-value <?= empty($encounter['social_history']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['social_history']) ? nl2br(htmlspecialchars($encounter['social_history'])) : 'None recorded' ?>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <div class="field-label">Family History</div>
                            <div class="field-value <?= empty($encounter['family_history']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['family_history']) ? nl2br(htmlspecialchars($encounter['family_history'])) : 'None recorded' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vital Signs -->
                <?php if ($vitals): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-heartbeat"></i> Vital Signs
                        </h3>
                        <div class="section-meta">
                            Recorded by: <?= htmlspecialchars($vitals['recorder_first_name'] . ' ' . $vitals['recorder_last_name']) ?>
                            on <?= date('M j, Y g:i A', strtotime($vitals['recorded_at'])) ?>
                        </div>
                    </div>
                    <div class="vitals-grid">
                        <?php if ($vitals['systolic_bp'] && $vitals['diastolic_bp']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['systolic_bp']) ?>/<?= htmlspecialchars($vitals['diastolic_bp']) ?></div>
                            <div class="vital-label">Blood Pressure</div>
                            <div class="vital-unit">mmHg</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vitals['heart_rate']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['heart_rate']) ?></div>
                            <div class="vital-label">Heart Rate</div>
                            <div class="vital-unit">bpm</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vitals['respiratory_rate']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['respiratory_rate']) ?></div>
                            <div class="vital-label">Respiratory Rate</div>
                            <div class="vital-unit">/min</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vitals['temperature']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['temperature']) ?></div>
                            <div class="vital-label">Temperature</div>
                            <div class="vital-unit">°C</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vitals['weight']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['weight']) ?></div>
                            <div class="vital-label">Weight</div>
                            <div class="vital-unit">kg</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vitals['height']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['height']) ?></div>
                            <div class="vital-label">Height</div>
                            <div class="vital-unit">cm</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vitals['bmi']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['bmi']) ?></div>
                            <div class="vital-label">BMI</div>
                            <div class="vital-unit">kg/m²</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vitals['oxygen_saturation']): ?>
                        <div class="vital-card">
                            <div class="vital-value"><?= htmlspecialchars($vitals['oxygen_saturation']) ?></div>
                            <div class="vital-label">O2 Saturation</div>
                            <div class="vital-unit">%</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($vitals['remarks'])): ?>
                    <div class="field-group">
                        <div class="field-label">Vital Signs Notes</div>
                        <div class="field-value"><?= nl2br(htmlspecialchars($vitals['remarks'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Physical Examination -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-user-md"></i> Physical Examination
                        </h3>
                    </div>
                    <div class="content-grid">
                        <div class="field-group full-width">
                            <div class="field-label">General Appearance</div>
                            <div class="field-value <?= empty($encounter['general_appearance']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['general_appearance']) ? nl2br(htmlspecialchars($encounter['general_appearance'])) : 'No general appearance notes recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Head & Neck</div>
                            <div class="field-value <?= empty($encounter['head_neck']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['head_neck']) ? nl2br(htmlspecialchars($encounter['head_neck'])) : 'No findings recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Cardiovascular</div>
                            <div class="field-value <?= empty($encounter['cardiovascular']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['cardiovascular']) ? nl2br(htmlspecialchars($encounter['cardiovascular'])) : 'No findings recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Respiratory</div>
                            <div class="field-value <?= empty($encounter['respiratory']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['respiratory']) ? nl2br(htmlspecialchars($encounter['respiratory'])) : 'No findings recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Abdominal</div>
                            <div class="field-value <?= empty($encounter['abdominal']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['abdominal']) ? nl2br(htmlspecialchars($encounter['abdominal'])) : 'No findings recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Neurological</div>
                            <div class="field-value <?= empty($encounter['neurological']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['neurological']) ? nl2br(htmlspecialchars($encounter['neurological'])) : 'No findings recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Extremities</div>
                            <div class="field-value <?= empty($encounter['extremities']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['extremities']) ? nl2br(htmlspecialchars($encounter['extremities'])) : 'No findings recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Skin</div>
                            <div class="field-value <?= empty($encounter['skin']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['skin']) ? nl2br(htmlspecialchars($encounter['skin'])) : 'No findings recorded' ?>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <div class="field-label">Other Findings</div>
                            <div class="field-value <?= empty($encounter['other_findings']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['other_findings']) ? nl2br(htmlspecialchars($encounter['other_findings'])) : 'No other findings recorded' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assessment & Plan -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-diagnoses"></i> Assessment & Plan
                        </h3>
                    </div>
                    <div class="content-grid">
                        <div class="field-group full-width">
                            <div class="field-label">Clinical Assessment</div>
                            <div class="field-value <?= empty($encounter['assessment']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['assessment']) ? nl2br(htmlspecialchars($encounter['assessment'])) : 'No clinical assessment recorded' ?>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <div class="field-label">Primary Diagnosis</div>
                            <div class="field-value <?= empty($encounter['diagnosis']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['diagnosis']) ? nl2br(htmlspecialchars($encounter['diagnosis'])) : 'No diagnosis recorded' ?>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <div class="field-label">Treatment Plan</div>
                            <div class="field-value <?= empty($encounter['treatment_plan']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['treatment_plan']) ? nl2br(htmlspecialchars($encounter['treatment_plan'])) : 'No treatment plan recorded' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Follow-up Date</div>
                            <div class="field-value <?= empty($encounter['follow_up_date']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['follow_up_date']) ? date('M j, Y', strtotime($encounter['follow_up_date'])) : 'No follow-up scheduled' ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Follow-up Instructions</div>
                            <div class="field-value <?= empty($encounter['follow_up_instructions']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['follow_up_instructions']) ? nl2br(htmlspecialchars($encounter['follow_up_instructions'])) : 'No follow-up instructions' ?>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <div class="field-label">Additional Notes</div>
                            <div class="field-value <?= empty($encounter['notes']) ? 'empty' : '' ?>">
                                <?= !empty($encounter['notes']) ? nl2br(htmlspecialchars($encounter['notes'])) : 'No additional notes' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prescriptions -->
            <div class="prescriptions-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-pills"></i> Prescriptions (<?= count($prescriptions) ?>)
                    </h3>
                </div>
                <?php if (!empty($prescriptions)): ?>
                    <div class="items-grid">
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div class="item-card">
                                <div class="item-header">
                                    <div class="item-title"><?= htmlspecialchars($prescription['medication_name']) ?></div>
                                    <div class="item-meta">
                                        Prescribed by: <?= htmlspecialchars($prescription['prescriber_first_name'] . ' ' . $prescription['prescriber_last_name']) ?>
                                        on <?= date('M j, Y', strtotime($prescription['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="item-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Dosage</div>
                                        <div class="detail-value"><?= htmlspecialchars($prescription['dosage']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Frequency</div>
                                        <div class="detail-value"><?= htmlspecialchars($prescription['frequency']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Duration</div>
                                        <div class="detail-value"><?= htmlspecialchars($prescription['duration']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Status</div>
                                        <div class="detail-value"><?= htmlspecialchars(ucwords($prescription['status'])) ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($prescription['instructions'])): ?>
                                <div class="field-group">
                                    <div class="field-label">Instructions</div>
                                    <div class="field-value"><?= nl2br(htmlspecialchars($prescription['instructions'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-pills fa-2x"></i>
                        <p>No prescriptions have been issued for this consultation.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lab Tests -->
            <div class="lab-tests-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-vial"></i> Laboratory Tests (<?= count($lab_tests) ?>)
                    </h3>
                </div>
                <?php if (!empty($lab_tests)): ?>
                    <div class="items-grid">
                        <?php foreach ($lab_tests as $lab_test): ?>
                            <div class="item-card">
                                <div class="item-header">
                                    <div class="item-title"><?= htmlspecialchars($lab_test['test_name']) ?></div>
                                    <div class="item-meta">
                                        Ordered by: <?= htmlspecialchars($lab_test['orderer_first_name'] . ' ' . $lab_test['orderer_last_name']) ?>
                                        on <?= date('M j, Y', strtotime($lab_test['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="item-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Status</div>
                                        <div class="detail-value"><?= htmlspecialchars(ucwords($lab_test['status'])) ?></div>
                                    </div>
                                    <?php if ($lab_test['sample_collected_at']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Sample Collected</div>
                                        <div class="detail-value"><?= date('M j, Y g:i A', strtotime($lab_test['sample_collected_at'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($lab_test['result_available_at']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Result Available</div>
                                        <div class="detail-value"><?= date('M j, Y g:i A', strtotime($lab_test['result_available_at'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($lab_test['instructions'])): ?>
                                <div class="field-group">
                                    <div class="field-label">Instructions</div>
                                    <div class="field-value"><?= nl2br(htmlspecialchars($lab_test['instructions'])) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($lab_test['results'])): ?>
                                <div class="field-group">
                                    <div class="field-label">Results</div>
                                    <div class="field-value"><?= nl2br(htmlspecialchars($lab_test['results'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-vial fa-2x"></i>
                        <p>No laboratory tests have been ordered for this consultation.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <?php if (in_array($employee_role, ['doctor', 'admin']) || 
                          ($encounter['status'] == 'in_progress' && $employee_role == 'nurse')): ?>
                    <a href="edit_consultation.php?id=<?= $encounter['encounter_id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Consultation
                    </a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </section>

    <script>
        // Print optimization
        window.addEventListener('beforeprint', function() {
            // Hide action buttons and navigation for printing
            document.querySelector('.action-buttons').style.display = 'none';
            document.querySelector('.quick-actions').style.display = 'none';
            document.querySelector('.topbar').style.display = 'none';
            document.querySelector('.back-button').style.display = 'none';
        });

        window.addEventListener('afterprint', function() {
            // Restore elements after printing
            document.querySelector('.action-buttons').style.display = 'flex';
            document.querySelector('.quick-actions').style.display = 'block';
            document.querySelector('.topbar').style.display = 'flex';
            document.querySelector('.back-button').style.display = 'block';
        });
    </script>
</body>

</html>