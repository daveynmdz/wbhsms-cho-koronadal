<?php
// Resolve path to root directory using realpath for consistent path format
$root_path = realpath(dirname(dirname(dirname(__FILE__))));

// Include authentication and config
require_once $root_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'session' . DIRECTORY_SEPARATOR . 'employee_session.php';
require_once $root_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

// Check if employee is logged in
if (!is_employee_logged_in()) {
    header("Location: /wbhsms-cho-koronadal/pages/management/login.php");
    exit();
}

// Get employee details with role lookup
$employee_id = get_employee_session('employee_id');
$employee_stmt = $conn->prepare("SELECT e.*, r.role_name as role FROM employees e LEFT JOIN roles r ON e.role_id = r.role_id WHERE e.employee_id = ?");
$employee_stmt->bind_param("i", $employee_id);
$employee_stmt->execute();
$employee_result = $employee_stmt->get_result();
$employee_details = $employee_result->fetch_assoc();

if (!$employee_details) {
    header("Location: /wbhsms-cho-koronadal/pages/management/login.php");
    exit();
}

$employee_role = strtolower($employee_details['role']);
$employee_name = $employee_details['first_name'] . ' ' . $employee_details['last_name'];

// Check if user has access to view consultations
$authorized_roles = ['doctor', 'nurse', 'admin', 'records_officer', 'bhw', 'dho'];
if (!in_array($employee_role, $authorized_roles)) {
    header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=unauthorized");
    exit();
}

// Get consultation_id from URL
$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$consultation_id) {
    header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=invalid_consultation");
    exit();
}

// Initialize variables
$consultation_data = null;
$patient_data = null;
$visit_data = null;
$vitals_data = null;
$attending_doctor = null;
$lab_orders = [];
$prescriptions = [];
$followup_appointments = [];
$error_message = '';

// Role-based permissions
$can_edit_consultation = in_array($employee_role, ['doctor', 'admin']);

// Load consultation data with related information
try {
    // Get consultation with patient, visit, and doctor information
    $consultation_stmt = $conn->prepare("
        SELECT c.*, p.first_name, p.last_name, p.username, p.date_of_birth, p.sex, p.contact_number,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               b.barangay_name, d.district_name,
               v.visit_type, v.visit_purpose, v.created_at as visit_date,
               doc.first_name as doctor_first_name, doc.last_name as doctor_last_name,
               doc.specialization as doctor_specialization
        FROM consultations c
        JOIN visits v ON c.visit_id = v.visit_id
        JOIN patients p ON c.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN districts d ON b.district_id = d.district_id
        LEFT JOIN employees doc ON c.attending_employee_id = doc.employee_id
        WHERE c.consultation_id = ?
    ");
    $consultation_stmt->bind_param("i", $consultation_id);
    $consultation_stmt->execute();
    $result = $consultation_stmt->get_result();
    $consultation_data = $result->fetch_assoc();
    
    if (!$consultation_data) {
        header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=consultation_not_found");
        exit();
    }
    
    // Check role-based access for specific patients
    if (in_array($employee_role, ['bhw', 'dho'])) {
        $has_access = false;
        
        if ($employee_role === 'bhw' && isset($employee_details['assigned_barangay_id'])) {
            $has_access = ($employee_details['assigned_barangay_id'] == $consultation_data['barangay_id']);
        } elseif ($employee_role === 'dho' && isset($employee_details['assigned_district_id'])) {
            $has_access = ($employee_details['assigned_district_id'] == $consultation_data['district_id']);
        }
        
        if (!$has_access) {
            header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=access_denied");
            exit();
        }
    }
    
    $patient_data = $consultation_data;
    $visit_data = $consultation_data;
    
    // Get vitals data
    $vitals_stmt = $conn->prepare("
        SELECT v.*, e.first_name as taken_by_first_name, e.last_name as taken_by_last_name
        FROM vitals v
        LEFT JOIN employees e ON v.taken_by = e.employee_id
        WHERE v.visit_id = ?
    ");
    $vitals_stmt->bind_param("i", $consultation_data['visit_id']);
    $vitals_stmt->execute();
    $vitals_result = $vitals_stmt->get_result();
    $vitals_data = $vitals_result->fetch_assoc();
    
    // Get lab orders
    $lab_stmt = $conn->prepare("
        SELECT l.*, e.first_name as ordered_by_first_name, e.last_name as ordered_by_last_name
        FROM lab_orders l
        LEFT JOIN employees e ON l.ordered_by = e.employee_id
        WHERE l.visit_id = ? OR l.consultation_id = ?
        ORDER BY l.created_at DESC
    ");
    $lab_stmt->bind_param("ii", $consultation_data['visit_id'], $consultation_id);
    $lab_stmt->execute();
    $lab_result = $lab_stmt->get_result();
    $lab_orders = $lab_result->fetch_all(MYSQLI_ASSOC);
    
    // Get prescriptions
    $prescription_stmt = $conn->prepare("
        SELECT p.*, e.first_name as prescribed_by_first_name, e.last_name as prescribed_by_last_name
        FROM prescriptions p
        LEFT JOIN employees e ON p.prescribed_by = e.employee_id
        WHERE p.visit_id = ? OR p.consultation_id = ?
        ORDER BY p.created_at DESC
    ");
    $prescription_stmt->bind_param("ii", $consultation_data['visit_id'], $consultation_id);
    $prescription_stmt->execute();
    $prescription_result = $prescription_stmt->get_result();
    $prescriptions = $prescription_result->fetch_all(MYSQLI_ASSOC);
    
    // Get follow-up appointments
    $followup_stmt = $conn->prepare("
        SELECT a.*, e.first_name as scheduled_by_first_name, e.last_name as scheduled_by_last_name
        FROM appointments a
        LEFT JOIN employees e ON a.created_by = e.employee_id
        WHERE a.patient_id = ? AND a.appointment_type = 'Follow-up'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 3
    ");
    $followup_stmt->bind_param("i", $consultation_data['patient_id']);
    $followup_stmt->execute();
    $followup_result = $followup_stmt->get_result();
    $followup_appointments = $followup_result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading consultation data: " . $e->getMessage();
}

// Include topbar for consistent navigation
require_once $root_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'topbar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>View Consultation | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .consultation-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0077b6;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-follow-up-scheduled {
            background: #e2e3e5;
            color: #383d41;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .vitals-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .vitals-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 600;
        }

        .vitals-value {
            font-size: 1.1rem;
            color: #495057;
            font-weight: 600;
        }

        .vitals-unit {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .clinical-notes {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            margin: 1rem 0;
        }

        .clinical-notes h4 {
            margin: 0 0 0.5rem 0;
            color: #007bff;
            font-size: 1rem;
        }

        .clinical-notes p {
            margin: 0;
            line-height: 1.5;
            color: #495057;
            white-space: pre-line;
        }

        .order-list {
            display: grid;
            gap: 1rem;
        }

        .order-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .order-title {
            font-weight: 600;
            color: #495057;
        }

        .order-date {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
            margin-top: 2rem;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .read-only-notice {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #1565c0;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .info-grid,
            .vitals-grid,
            .order-details {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php 
    // Render topbar
    renderTopbar([
        'title' => 'View Consultation',
        'back_url' => 'index.php',
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <div class="consultation-container">

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (in_array($employee_role, ['records_officer', 'bhw', 'dho'])): ?>
                <div class="read-only-notice">
                    <i class="fas fa-eye"></i>
                    <strong>Read-Only View:</strong> You have view-only access to this consultation record.
                    Role: <?= ucfirst(str_replace('_', ' ', $employee_role)) ?>
                </div>
            <?php endif; ?>

            <?php if ($consultation_data): ?>

                <!-- Patient Information -->
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Patient Information
                    </h3>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Patient Name</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Patient ID</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['username']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age / Sex</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['age']) ?> years / <?= htmlspecialchars($patient_data['sex']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['contact_number'] ?: 'Not provided') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <?= htmlspecialchars($patient_data['barangay_name']) ?><?= $patient_data['district_name'] ? ', ' . htmlspecialchars($patient_data['district_name']) : '' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visit Information -->
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-calendar"></i> Visit Information
                    </h3>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Visit ID</div>
                            <div class="info-value">#<?= htmlspecialchars($visit_data['visit_id']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Visit Date</div>
                            <div class="info-value"><?= date('M j, Y g:i A', strtotime($visit_data['visit_date'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Visit Type</div>
                            <div class="info-value"><?= htmlspecialchars($visit_data['visit_type'] ?: 'Regular Visit') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Visit Purpose</div>
                            <div class="info-value"><?= htmlspecialchars($visit_data['visit_purpose'] ?: 'General Consultation') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Attending Physician</div>
                            <div class="info-value">
                                <?php if ($consultation_data['doctor_first_name']): ?>
                                    Dr. <?= htmlspecialchars($consultation_data['doctor_first_name'] . ' ' . $consultation_data['doctor_last_name']) ?>
                                    <?php if ($consultation_data['doctor_specialization']): ?>
                                        <br><small>(<?= htmlspecialchars($consultation_data['doctor_specialization']) ?>)</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Not assigned
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Consultation Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?= str_replace(' ', '-', strtolower($consultation_data['consultation_status'])) ?>">
                                    <?= htmlspecialchars(ucwords($consultation_data['consultation_status'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vital Signs -->
                <?php if ($vitals_data): ?>
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-heartbeat"></i> Vital Signs
                            <small style="margin-left: auto; font-size: 0.8rem; color: #6c757d;">
                                Taken by: <?= htmlspecialchars($vitals_data['taken_by_first_name'] . ' ' . $vitals_data['taken_by_last_name']) ?> 
                                on <?= date('M j, Y g:i A', strtotime($vitals_data['created_at'])) ?>
                            </small>
                        </h3>

                        <div class="vitals-grid">
                            <?php if ($vitals_data['blood_pressure']): ?>
                                <div class="vitals-item">
                                    <div class="vitals-label">Blood Pressure</div>
                                    <div class="vitals-value"><?= htmlspecialchars($vitals_data['blood_pressure']) ?> <span class="vitals-unit">mmHg</span></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($vitals_data['heart_rate']): ?>
                                <div class="vitals-item">
                                    <div class="vitals-label">Heart Rate</div>
                                    <div class="vitals-value"><?= htmlspecialchars($vitals_data['heart_rate']) ?> <span class="vitals-unit">bpm</span></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($vitals_data['temperature']): ?>
                                <div class="vitals-item">
                                    <div class="vitals-label">Temperature</div>
                                    <div class="vitals-value"><?= htmlspecialchars($vitals_data['temperature']) ?> <span class="vitals-unit">°C</span></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($vitals_data['respiratory_rate']): ?>
                                <div class="vitals-item">
                                    <div class="vitals-label">Respiratory Rate</div>
                                    <div class="vitals-value"><?= htmlspecialchars($vitals_data['respiratory_rate']) ?> <span class="vitals-unit">rpm</span></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($vitals_data['height']): ?>
                                <div class="vitals-item">
                                    <div class="vitals-label">Height</div>
                                    <div class="vitals-value"><?= htmlspecialchars($vitals_data['height']) ?> <span class="vitals-unit">cm</span></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($vitals_data['weight']): ?>
                                <div class="vitals-item">
                                    <div class="vitals-label">Weight</div>
                                    <div class="vitals-value"><?= htmlspecialchars($vitals_data['weight']) ?> <span class="vitals-unit">kg</span></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Consultation Details -->
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-stethoscope"></i> Consultation Details
                        <small style="margin-left: auto; font-size: 0.8rem; color: #6c757d;">
                            <?= date('M j, Y g:i A', strtotime($consultation_data['consultation_date'])) ?>
                        </small>
                    </h3>

                    <?php if ($consultation_data['chief_complaint']): ?>
                        <div class="clinical-notes">
                            <h4>Chief Complaint</h4>
                            <p><?= htmlspecialchars($consultation_data['chief_complaint']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($consultation_data['diagnosis']): ?>
                        <div class="clinical-notes">
                            <h4>Diagnosis</h4>
                            <p><?= htmlspecialchars($consultation_data['diagnosis']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($consultation_data['treatment_plan']): ?>
                        <div class="clinical-notes">
                            <h4>Treatment Plan</h4>
                            <p><?= htmlspecialchars($consultation_data['treatment_plan']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($consultation_data['remarks']): ?>
                        <div class="clinical-notes">
                            <h4>Additional Remarks</h4>
                            <p><?= htmlspecialchars($consultation_data['remarks']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Lab Orders -->
                <?php if (!empty($lab_orders)): ?>
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-flask"></i> Laboratory Orders (<?= count($lab_orders) ?>)
                        </h3>

                        <div class="order-list">
                            <?php foreach ($lab_orders as $lab): ?>
                                <div class="order-item">
                                    <div class="order-header">
                                        <div class="order-title"><?= htmlspecialchars($lab['test_name']) ?></div>
                                        <div class="order-date"><?= date('M j, Y g:i A', strtotime($lab['created_at'])) ?></div>
                                    </div>
                                    <div class="order-details">
                                        <div class="info-item">
                                            <div class="info-label">Status</div>
                                            <div class="info-value">
                                                <span class="status-badge status-<?= str_replace(' ', '-', strtolower($lab['status'])) ?>">
                                                    <?= htmlspecialchars(ucwords($lab['status'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Ordered By</div>
                                            <div class="info-value">Dr. <?= htmlspecialchars($lab['ordered_by_first_name'] . ' ' . $lab['ordered_by_last_name']) ?></div>
                                        </div>
                                        <?php if ($lab['special_instructions']): ?>
                                            <div class="info-item" style="grid-column: 1 / -1;">
                                                <div class="info-label">Instructions</div>
                                                <div class="info-value"><?= htmlspecialchars($lab['special_instructions']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Prescriptions -->
                <?php if (!empty($prescriptions)): ?>
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-pills"></i> Prescriptions (<?= count($prescriptions) ?>)
                        </h3>

                        <div class="order-list">
                            <?php foreach ($prescriptions as $prescription): ?>
                                <div class="order-item">
                                    <div class="order-header">
                                        <div class="order-title"><?= htmlspecialchars($prescription['medication_name']) ?></div>
                                        <div class="order-date"><?= date('M j, Y g:i A', strtotime($prescription['created_at'])) ?></div>
                                    </div>
                                    <div class="order-details">
                                        <div class="info-item">
                                            <div class="info-label">Dosage</div>
                                            <div class="info-value"><?= htmlspecialchars($prescription['dosage']) ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Frequency</div>
                                            <div class="info-value"><?= htmlspecialchars($prescription['frequency']) ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Prescribed By</div>
                                            <div class="info-value">Dr. <?= htmlspecialchars($prescription['prescribed_by_first_name'] . ' ' . $prescription['prescribed_by_last_name']) ?></div>
                                        </div>
                                        <?php if ($prescription['instructions']): ?>
                                            <div class="info-item" style="grid-column: 1 / -1;">
                                                <div class="info-label">Instructions</div>
                                                <div class="info-value"><?= htmlspecialchars($prescription['instructions']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Follow-up Appointments -->
                <?php if (!empty($followup_appointments)): ?>
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-check"></i> Follow-up Appointments (<?= count($followup_appointments) ?>)
                        </h3>

                        <div class="order-list">
                            <?php foreach ($followup_appointments as $followup): ?>
                                <div class="order-item">
                                    <div class="order-header">
                                        <div class="order-title"><?= date('M j, Y g:i A', strtotime($followup['appointment_date'] . ' ' . $followup['appointment_time'])) ?></div>
                                        <div class="order-date">Scheduled <?= date('M j, Y', strtotime($followup['created_at'])) ?></div>
                                    </div>
                                    <div class="order-details">
                                        <div class="info-item">
                                            <div class="info-label">Status</div>
                                            <div class="info-value">
                                                <span class="status-badge status-<?= strtolower($followup['status']) ?>">
                                                    <?= htmlspecialchars(ucwords($followup['status'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Reason</div>
                                            <div class="info-value"><?= htmlspecialchars($followup['reason']) ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Scheduled By</div>
                                            <div class="info-value">Dr. <?= htmlspecialchars($followup['scheduled_by_first_name'] . ' ' . $followup['scheduled_by_last_name']) ?></div>
                                        </div>
                                        <?php if ($followup['notes']): ?>
                                            <div class="info-item" style="grid-column: 1 / -1;">
                                                <div class="info-label">Notes</div>
                                                <div class="info-value"><?= htmlspecialchars($followup['notes']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    
                    <?php if ($can_edit_consultation): ?>
                        <a href="edit_consultation.php?id=<?= $consultation_id ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Consultation
                        </a>
                    <?php endif; ?>
                    
                    <a href="consultation.php?visit_id=<?= $consultation_data['visit_id'] ?>" class="btn btn-success">
                        <i class="fas fa-stethoscope"></i> Full Consultation View
                    </a>
                </div>

            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-file-medical fa-3x"></i>
                    <h3>Consultation Not Found</h3>
                    <p>The requested consultation could not be found or you don't have access to view it.</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </section>
</body>

</html>