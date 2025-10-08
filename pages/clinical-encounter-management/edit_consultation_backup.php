<?php
// edit_consultation.php - Edit Clinical Encounter Form
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
$authorized_roles = ['doctor', 'nurse', 'admin'];
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

// Initialize variables
$success_message = '';
$error_message = '';
$encounter = null;
$patient = null;
$vitals = null;

// Fetch encounter details
try {
    $stmt = $conn->prepare("
        SELECT e.*, 
               p.first_name as patient_first_name, p.middle_name as patient_middle_name, 
               p.last_name as patient_last_name, p.username as patient_id_display,
               p.date_of_birth, p.sex, p.contact_number, p.email, p.address,
               b.barangay_name,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as patient_age
        FROM clinical_encounters e
        JOIN patients p ON e.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
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
    
    // Check permissions
    if ($employee_role == 'nurse' && $encounter['status'] != 'in_progress') {
        $_SESSION['snackbar_message'] = 'You can only edit consultations that are in progress.';
        header("Location: view_consultation.php?id=$encounter_id");
        exit();
    }
    
    // Fetch vitals if available
    if ($encounter['vitals_id']) {
        $stmt = $conn->prepare("SELECT * FROM vitals WHERE vitals_id = ?");
        $stmt->bind_param('i', $encounter['vitals_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $vitals = $result->fetch_assoc();
    }
    
} catch (Exception $e) {
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_consultation') {
        try {
            $conn->begin_transaction();
            
            // Get form data
            $chief_complaint = trim($_POST['chief_complaint'] ?? '');
            $history_present_illness = trim($_POST['history_present_illness'] ?? '');
            $past_medical_history = trim($_POST['past_medical_history'] ?? '');
            $medications = trim($_POST['medications'] ?? '');
            $allergies = trim($_POST['allergies'] ?? '');
            $social_history = trim($_POST['social_history'] ?? '');
            $family_history = trim($_POST['family_history'] ?? '');
            
            // Physical examination
            $general_appearance = trim($_POST['general_appearance'] ?? '');
            $vital_signs_notes = trim($_POST['vital_signs_notes'] ?? '');
            $head_neck = trim($_POST['head_neck'] ?? '');
            $cardiovascular = trim($_POST['cardiovascular'] ?? '');
            $respiratory = trim($_POST['respiratory'] ?? '');
            $abdominal = trim($_POST['abdominal'] ?? '');
            $neurological = trim($_POST['neurological'] ?? '');
            $extremities = trim($_POST['extremities'] ?? '');
            $skin = trim($_POST['skin'] ?? '');
            $other_findings = trim($_POST['other_findings'] ?? '');
            
            // Assessment and plan
            $assessment = trim($_POST['assessment'] ?? '');
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $treatment_plan = trim($_POST['treatment_plan'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
            $follow_up_instructions = trim($_POST['follow_up_instructions'] ?? '');
            $status = $_POST['status'] ?? $encounter['status'];
            
            // Vital signs (optional)
            $systolic_bp = !empty($_POST['systolic_bp']) ? (int)$_POST['systolic_bp'] : null;
            $diastolic_bp = !empty($_POST['diastolic_bp']) ? (int)$_POST['diastolic_bp'] : null;
            $heart_rate = !empty($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : null;
            $respiratory_rate = !empty($_POST['respiratory_rate']) ? (int)$_POST['respiratory_rate'] : null;
            $temperature = !empty($_POST['temperature']) ? (float)$_POST['temperature'] : null;
            $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
            $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
            $oxygen_saturation = !empty($_POST['oxygen_saturation']) ? (float)$_POST['oxygen_saturation'] : null;
            
            // Calculate BMI if both weight and height are provided
            $bmi = null;
            if ($weight && $height) {
                $height_m = $height / 100; // Convert cm to meters
                $bmi = round($weight / ($height_m * $height_m), 2);
            }
            
            // Validation
            if (empty($chief_complaint)) {
                throw new Exception('Chief complaint is required.');
            }
            
            // Update or create vitals
            $vitals_id = $encounter['vitals_id'];
            if ($systolic_bp || $diastolic_bp || $heart_rate || $respiratory_rate || $temperature || $weight || $height || $oxygen_saturation || !empty($vital_signs_notes)) {
                if ($vitals_id) {
                    // Update existing vitals
                    $stmt = $conn->prepare("
                        UPDATE vitals SET
                            systolic_bp = ?, diastolic_bp = ?, heart_rate = ?, 
                            respiratory_rate = ?, temperature = ?, weight = ?, height = ?, bmi = ?,
                            oxygen_saturation = ?, remarks = ?, recorded_by = ?, recorded_at = NOW()
                        WHERE vitals_id = ?
                    ");
                    $stmt->bind_param(
                        'iiiidddddisi', 
                        $systolic_bp, $diastolic_bp, $heart_rate, 
                        $respiratory_rate, $temperature, $weight, $height, $bmi,
                        $oxygen_saturation, $vital_signs_notes, $employee_id, $vitals_id
                    );
                    $stmt->execute();
                } else {
                    // Create new vitals
                    $stmt = $conn->prepare("
                        INSERT INTO vitals (
                            patient_id, systolic_bp, diastolic_bp, heart_rate, 
                            respiratory_rate, temperature, weight, height, bmi,
                            oxygen_saturation, recorded_by, remarks
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        'iiiidddddiss', 
                        $encounter['patient_id'], $systolic_bp, $diastolic_bp, $heart_rate, 
                        $respiratory_rate, $temperature, $weight, $height, $bmi,
                        $oxygen_saturation, $employee_id, $vital_signs_notes
                    );
                    $stmt->execute();
                    $vitals_id = $conn->insert_id;
                }
            }
            
            // Determine doctor_id
            $doctor_id = $encounter['doctor_id'];
            if ($employee_role === 'doctor' && !$doctor_id) {
                $doctor_id = $employee_id;
            }
            
            // Update clinical encounter
            $stmt = $conn->prepare("
                UPDATE clinical_encounters SET
                    doctor_id = ?, chief_complaint = ?, history_present_illness = ?,
                    past_medical_history = ?, medications = ?, allergies = ?, social_history = ?, family_history = ?,
                    general_appearance = ?, vital_signs_notes = ?, head_neck = ?, cardiovascular = ?, respiratory = ?,
                    abdominal = ?, neurological = ?, extremities = ?, skin = ?, other_findings = ?,
                    assessment = ?, diagnosis = ?, treatment_plan = ?, notes = ?, follow_up_date = ?, follow_up_instructions = ?,
                    vitals_id = ?, status = ?, updated_at = NOW()
                WHERE encounter_id = ?
            ");
            
            $stmt->bind_param(
                'issssssssssssssssssssissii',
                $doctor_id, $chief_complaint, $history_present_illness,
                $past_medical_history, $medications, $allergies, $social_history, $family_history,
                $general_appearance, $vital_signs_notes, $head_neck, $cardiovascular, $respiratory,
                $abdominal, $neurological, $extremities, $skin, $other_findings,
                $assessment, $diagnosis, $treatment_plan, $notes, $follow_up_date, $follow_up_instructions,
                $vitals_id, $status, $encounter_id
            );
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['snackbar_message'] = "Clinical encounter updated successfully!";
            
            // Redirect to view the updated encounter
            header("Location: view_consultation.php?id=$encounter_id");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Edit Consultation - <?= htmlspecialchars($encounter['patient_first_name'] . ' ' . $encounter['patient_last_name']) ?> | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .patient-info-card {
            background: #e8f5e8;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 2rem;
        }

        .patient-info-header {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .patient-info-details {
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

        .form-sections {
            display: grid;
            gap: 2rem;
        }

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0077b6;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-grid.four-column {
            grid-template-columns: repeat(4, 1fr);
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
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }

        .physical-exam-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
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
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .status-selection {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
            margin-bottom: 1rem;
        }

        .status-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .status-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-option input[type="radio"] {
            width: auto;
            margin: 0;
        }

        @media (max-width: 768px) {
            .form-grid.two-column,
            .form-grid.three-column,
            .form-grid.four-column {
                grid-template-columns: 1fr;
            }

            .vitals-grid {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            }

            .physical-exam-grid {
                grid-template-columns: 1fr;
            }

            .patient-info-details {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .status-options {
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
        'title' => 'Edit Consultation',
        'back_url' => "view_consultation.php?id=$encounter_id",
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <?php 
        // Render back button with modal
        renderBackButton([
            'back_url' => "view_consultation.php?id=$encounter_id",
            'button_text' => '← Back to View',
            'modal_title' => 'Cancel Editing?',
            'modal_message' => 'Are you sure you want to cancel editing? Any unsaved changes will be lost.',
            'confirm_text' => 'Yes, Cancel',
            'stay_text' => 'Keep Editing'
        ]);
        ?>

        <div class="profile-wrapper">
            <!-- Reminders Box -->
            <div class="reminders-box">
                <strong>Editing Guidelines:</strong>
                <ul>
                    <li>Make sure all information is accurate before saving changes.</li>
                    <li>Update vital signs if new measurements were taken.</li>
                    <li>Complete diagnosis and treatment plan if this is a doctor consultation.</li>
                    <li>Update consultation status appropriately.</li>
                    <li>Document any significant changes in the notes section.</li>
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

            <!-- Patient Information -->
            <div class="patient-info-card">
                <div class="patient-info-header">
                    <i class="fas fa-user-check"></i> Patient Information
                </div>
                <div class="patient-info-details">
                    <div class="info-item">
                        <div class="info-label">Name</div>
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
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?= date('M j, Y', strtotime($encounter['date_of_birth'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contact</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['contact_number'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Barangay</div>
                        <div class="info-value"><?= htmlspecialchars($encounter['barangay_name'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <form method="POST" class="consultation-form">
                <input type="hidden" name="action" value="update_consultation">

                <div class="form-sections">
                    <!-- Status Selection (for doctors and admins) -->
                    <?php if (in_array($employee_role, ['doctor', 'admin'])): ?>
                    <div class="status-selection">
                        <h4><i class="fas fa-flag"></i> Consultation Status</h4>
                        <div class="status-options">
                            <div class="status-option">
                                <input type="radio" id="status_in_progress" name="status" value="in_progress" 
                                    <?= $encounter['status'] == 'in_progress' ? 'checked' : '' ?>>
                                <label for="status_in_progress">In Progress</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="status_completed" name="status" value="completed" 
                                    <?= $encounter['status'] == 'completed' ? 'checked' : '' ?>>
                                <label for="status_completed">Completed</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="status_follow_up" name="status" value="follow_up_required" 
                                    <?= $encounter['status'] == 'follow_up_required' ? 'checked' : '' ?>>
                                <label for="status_follow_up">Follow-up Required</label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Chief Complaint and History -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-list"></i> Chief Complaint & History
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="chief_complaint">Chief Complaint <span class="required">*</span></label>
                                <textarea id="chief_complaint" name="chief_complaint" required
                                          placeholder="What brings the patient in today? (primary symptoms/concerns)"><?= htmlspecialchars($encounter['chief_complaint']) ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label for="history_present_illness">History of Present Illness</label>
                                <textarea id="history_present_illness" name="history_present_illness"
                                          placeholder="Detailed description of the current condition (onset, duration, character, etc.)"><?= htmlspecialchars($encounter['history_present_illness']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="past_medical_history">Past Medical History</label>
                                <textarea id="past_medical_history" name="past_medical_history"
                                          placeholder="Previous illnesses, surgeries, hospitalizations"><?= htmlspecialchars($encounter['past_medical_history']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="medications">Current Medications</label>
                                <textarea id="medications" name="medications"
                                          placeholder="List current medications and dosages"><?= htmlspecialchars($encounter['medications']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="allergies">Allergies</label>
                                <textarea id="allergies" name="allergies"
                                          placeholder="Drug allergies, food allergies, environmental allergies"><?= htmlspecialchars($encounter['allergies']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="social_history">Social History</label>
                                <textarea id="social_history" name="social_history"
                                          placeholder="Smoking, alcohol, occupation, living situation"><?= htmlspecialchars($encounter['social_history']) ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label for="family_history">Family History</label>
                                <textarea id="family_history" name="family_history"
                                          placeholder="Relevant family medical history"><?= htmlspecialchars($encounter['family_history']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Vital Signs -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-heartbeat"></i> Vital Signs (Optional)
                        </h3>
                        <div class="vitals-grid">
                            <div class="form-group">
                                <label for="systolic_bp">Systolic BP (mmHg)</label>
                                <input type="number" id="systolic_bp" name="systolic_bp" min="60" max="300" 
                                       value="<?= htmlspecialchars($vitals['systolic_bp'] ?? '') ?>" placeholder="120">
                            </div>
                            <div class="form-group">
                                <label for="diastolic_bp">Diastolic BP (mmHg)</label>
                                <input type="number" id="diastolic_bp" name="diastolic_bp" min="40" max="200" 
                                       value="<?= htmlspecialchars($vitals['diastolic_bp'] ?? '') ?>" placeholder="80">
                            </div>
                            <div class="form-group">
                                <label for="heart_rate">Heart Rate (bpm)</label>
                                <input type="number" id="heart_rate" name="heart_rate" min="30" max="200" 
                                       value="<?= htmlspecialchars($vitals['heart_rate'] ?? '') ?>" placeholder="72">
                            </div>
                            <div class="form-group">
                                <label for="respiratory_rate">Respiratory Rate (/min)</label>
                                <input type="number" id="respiratory_rate" name="respiratory_rate" min="8" max="60" 
                                       value="<?= htmlspecialchars($vitals['respiratory_rate'] ?? '') ?>" placeholder="18">
                            </div>
                            <div class="form-group">
                                <label for="temperature">Temperature (°C)</label>
                                <input type="number" id="temperature" name="temperature" step="0.1" min="30" max="45" 
                                       value="<?= htmlspecialchars($vitals['temperature'] ?? '') ?>" placeholder="36.5">
                            </div>
                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" id="weight" name="weight" step="0.1" min="1" max="500" 
                                       value="<?= htmlspecialchars($vitals['weight'] ?? '') ?>" placeholder="70.0">
                            </div>
                            <div class="form-group">
                                <label for="height">Height (cm)</label>
                                <input type="number" id="height" name="height" step="0.1" min="50" max="250" 
                                       value="<?= htmlspecialchars($vitals['height'] ?? '') ?>" placeholder="170.0">
                            </div>
                            <div class="form-group">
                                <label for="oxygen_saturation">O2 Saturation (%)</label>
                                <input type="number" id="oxygen_saturation" name="oxygen_saturation" step="0.1" min="70" max="100" 
                                       value="<?= htmlspecialchars($vitals['oxygen_saturation'] ?? '') ?>" placeholder="98">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="vital_signs_notes">Vital Signs Notes</label>
                            <textarea id="vital_signs_notes" name="vital_signs_notes"
                                      placeholder="Additional notes about vital signs"><?= htmlspecialchars($encounter['vital_signs_notes']) ?></textarea>
                        </div>
                    </div>

                    <!-- Physical Examination -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-md"></i> Physical Examination
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="general_appearance">General Appearance</label>
                                <textarea id="general_appearance" name="general_appearance"
                                          placeholder="Overall appearance, distress level, mental status"><?= htmlspecialchars($encounter['general_appearance']) ?></textarea>
                            </div>
                        </div>
                        <div class="physical-exam-grid">
                            <div class="form-group">
                                <label for="head_neck">Head & Neck</label>
                                <textarea id="head_neck" name="head_neck"
                                          placeholder="HEENT examination findings"><?= htmlspecialchars($encounter['head_neck']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="cardiovascular">Cardiovascular</label>
                                <textarea id="cardiovascular" name="cardiovascular"
                                          placeholder="Heart sounds, murmurs, rhythm"><?= htmlspecialchars($encounter['cardiovascular']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="respiratory">Respiratory</label>
                                <textarea id="respiratory" name="respiratory"
                                          placeholder="Lung sounds, breathing pattern"><?= htmlspecialchars($encounter['respiratory']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="abdominal">Abdominal</label>
                                <textarea id="abdominal" name="abdominal"
                                          placeholder="Inspection, palpation, bowel sounds"><?= htmlspecialchars($encounter['abdominal']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="neurological">Neurological</label>
                                <textarea id="neurological" name="neurological"
                                          placeholder="Mental status, reflexes, motor/sensory"><?= htmlspecialchars($encounter['neurological']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="extremities">Extremities</label>
                                <textarea id="extremities" name="extremities"
                                          placeholder="Edema, pulses, range of motion"><?= htmlspecialchars($encounter['extremities']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="skin">Skin</label>
                                <textarea id="skin" name="skin"
                                          placeholder="Color, lesions, rashes"><?= htmlspecialchars($encounter['skin']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="other_findings">Other Findings</label>
                                <textarea id="other_findings" name="other_findings"
                                          placeholder="Additional examination findings"><?= htmlspecialchars($encounter['other_findings']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Assessment & Plan -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-diagnoses"></i> Assessment & Plan
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="assessment">Clinical Assessment</label>
                                <textarea id="assessment" name="assessment"
                                          placeholder="Clinical impression and differential diagnosis"><?= htmlspecialchars($encounter['assessment']) ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label for="diagnosis">Primary Diagnosis</label>
                                <textarea id="diagnosis" name="diagnosis"
                                          placeholder="Primary and secondary diagnoses (ICD-10 codes if available)"><?= htmlspecialchars($encounter['diagnosis']) ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label for="treatment_plan">Treatment Plan</label>
                                <textarea id="treatment_plan" name="treatment_plan"
                                          placeholder="Medications, procedures, lifestyle modifications, referrals"><?= htmlspecialchars($encounter['treatment_plan']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="follow_up_date">Follow-up Date</label>
                                <input type="date" id="follow_up_date" name="follow_up_date" 
                                       value="<?= htmlspecialchars($encounter['follow_up_date']) ?>"
                                       min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label for="follow_up_instructions">Follow-up Instructions</label>
                                <textarea id="follow_up_instructions" name="follow_up_instructions"
                                          placeholder="When to return, what to watch for"><?= htmlspecialchars($encounter['follow_up_instructions']) ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label for="notes">Additional Notes</label>
                                <textarea id="notes" name="notes"
                                          placeholder="Any additional notes or comments"><?= htmlspecialchars($encounter['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="view_consultation.php?id=<?= $encounter_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Consultation
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                // Set initial height
                textarea.style.height = 'auto';
                textarea.style.height = (textarea.scrollHeight) + 'px';
                
                // Add input listener for auto-resize
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });

            // BMI calculation
            const weightInput = document.getElementById('weight');
            const heightInput = document.getElementById('height');
            
            if (weightInput && heightInput) {
                function calculateBMI() {
                    const weight = parseFloat(weightInput.value);
                    const height = parseFloat(heightInput.value);
                    
                    if (weight && height) {
                        const heightM = height / 100;
                        const bmi = weight / (heightM * heightM);
                        
                        // You can add a BMI display element if needed
                        console.log('BMI:', bmi.toFixed(2));
                    }
                }
                
                weightInput.addEventListener('input', calculateBMI);
                heightInput.addEventListener('input', calculateBMI);
            }

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const chiefComplaint = document.getElementById('chief_complaint').value.trim();
                    
                    if (!chiefComplaint) {
                        e.preventDefault();
                        alert('Chief complaint is required.');
                        document.getElementById('chief_complaint').focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>

</html>