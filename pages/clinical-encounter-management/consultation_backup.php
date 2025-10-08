<?php
// consultation.php - Clinical Encounter Form for Doctors and Nurses
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

// Initialize variables
$success_message = '';
$error_message = '';
$patient_id = $_GET['patient_id'] ?? '';
$selected_patient = null;

// If patient_id is provided, fetch patient info
if ($patient_id) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, b.barangay_name, 
                   TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
            FROM patients p 
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
            WHERE p.patient_id = ? AND p.status = 'active'
        ");
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_patient = $result->fetch_assoc();
    } catch (Exception $e) {
        $error_message = "Error loading patient information.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_consultation') {
        try {
            $conn->begin_transaction();
            
            // Get form data
            $patient_id = (int)($_POST['patient_id'] ?? 0);
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
            if (!$patient_id) {
                throw new Exception('Please select a valid patient.');
            }
            if (empty($chief_complaint)) {
                throw new Exception('Chief complaint is required.');
            }
            
            // Determine doctor_id based on role
            $doctor_id = null;
            if ($employee_role === 'doctor') {
                $doctor_id = $employee_id;
            }
            
            // Insert vitals if provided
            $vitals_id = null;
            if ($systolic_bp || $diastolic_bp || $heart_rate || $respiratory_rate || $temperature || $weight || $height || $oxygen_saturation) {
                $stmt = $conn->prepare("
                    INSERT INTO vitals (
                        patient_id, systolic_bp, diastolic_bp, heart_rate, 
                        respiratory_rate, temperature, weight, height, bmi,
                        oxygen_saturation, recorded_by, remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'iiiidddddiss', 
                    $patient_id, $systolic_bp, $diastolic_bp, $heart_rate, 
                    $respiratory_rate, $temperature, $weight, $height, $bmi,
                    $oxygen_saturation, $employee_id, $vital_signs_notes
                );
                $stmt->execute();
                $vitals_id = $conn->insert_id;
            }
            
            // Insert clinical encounter
            $stmt = $conn->prepare("
                INSERT INTO clinical_encounters (
                    patient_id, doctor_id, chief_complaint, history_present_illness,
                    past_medical_history, medications, allergies, social_history, family_history,
                    general_appearance, vital_signs_notes, head_neck, cardiovascular, respiratory,
                    abdominal, neurological, extremities, skin, other_findings,
                    assessment, diagnosis, treatment_plan, notes, follow_up_date, follow_up_instructions,
                    vitals_id, created_by, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $initial_status = ($employee_role === 'doctor' && !empty($diagnosis)) ? 'completed' : 'in_progress';
            
            $stmt->bind_param(
                'iisssssssssssssssssssssissi',
                $patient_id, $doctor_id, $chief_complaint, $history_present_illness,
                $past_medical_history, $medications, $allergies, $social_history, $family_history,
                $general_appearance, $vital_signs_notes, $head_neck, $cardiovascular, $respiratory,
                $abdominal, $neurological, $extremities, $skin, $other_findings,
                $assessment, $diagnosis, $treatment_plan, $notes, $follow_up_date, $follow_up_instructions,
                $vitals_id, $employee_id, $initial_status
            );
            $stmt->execute();
            
            $encounter_id = $conn->insert_id;
            
            $conn->commit();
            $_SESSION['snackbar_message'] = "Clinical encounter created successfully!";
            
            // Redirect to view the created encounter
            header("Location: view_consultation.php?id=$encounter_id");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Patient search functionality
$search_query = $_GET['search'] ?? '';
$patients = [];

if ($search_query) {
    try {
        $stmt = $conn->prepare("
            SELECT p.patient_id, p.username, p.first_name, p.middle_name, p.last_name, 
                   p.date_of_birth, p.sex, p.contact_number, b.barangay_name,
                   TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
            FROM patients p 
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
            WHERE (p.username LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? 
                   OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?)
            AND p.status = 'active'
            ORDER BY p.last_name, p.first_name
            LIMIT 10
        ");
        
        $search_term = "%$search_query%";
        $stmt->bind_param('ssss', $search_term, $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        $patients = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        // Ignore search errors
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Clinical Consultation | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .patient-search-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .search-form .form-group {
            flex: 1;
        }

        .patient-results {
            margin-top: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .patient-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .patient-card:hover {
            border-color: #0077b6;
            background: #f0f8ff;
        }

        .patient-card.selected {
            border-color: #28a745;
            background: #f8fff8;
        }

        .patient-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .patient-name {
            font-weight: 600;
            color: #0077b6;
            font-size: 1.1em;
        }

        .patient-id {
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85em;
            color: #6c757d;
            margin-left: auto;
        }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            font-size: 0.9em;
            color: #6c757d;
        }

        .consultation-form {
            display: none;
            opacity: 0.5;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .consultation-form.enabled {
            display: block;
            opacity: 1;
            pointer-events: auto;
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

        .selected-patient-info {
            background: #e8f5e8;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 1.5rem;
        }

        .patient-info-header {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 0.5rem;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .physical-exam-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }

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
        }

        .empty-search {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <?php 
    // Render snackbar notification system
    renderSnackbar();
    
    // Render topbar
    renderTopbar([
        'title' => 'Clinical Consultation',
        'back_url' => 'index.php',
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <?php 
        // Render back button with modal
        renderBackButton([
            'back_url' => 'index.php',
            'button_text' => '← Back to Encounters',
            'modal_title' => 'Cancel Consultation?',
            'modal_message' => 'Are you sure you want to go back? Any unsaved changes will be lost.',
            'confirm_text' => 'Yes, Cancel',
            'stay_text' => 'Stay'
        ]);
        ?>

        <div class="profile-wrapper">
            <!-- Reminders Box -->
            <div class="reminders-box">
                <strong>Clinical Consultation Guidelines:</strong>
                <ul>
                    <li>Search and select the patient first before starting the consultation.</li>
                    <li>Complete the chief complaint and relevant history sections.</li>
                    <li>Document physical examination findings systematically.</li>
                    <li>Provide clear assessment and treatment plan.</li>
                    <li>Vital signs recording is optional but recommended.</li>
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

            <!-- Patient Search -->
            <?php if (!$selected_patient): ?>
            <div class="patient-search-container">
                <h3><i class="fas fa-search"></i> Search Patient</h3>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="search">Patient Search</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>"
                               placeholder="Enter patient name or ID..." required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>

                <?php if ($search_query): ?>
                    <div class="patient-results">
                        <?php if (!empty($patients)): ?>
                            <h4>Found <?= count($patients) ?> patient(s):</h4>
                            <?php foreach ($patients as $patient): ?>
                                <div class="patient-card" onclick="selectPatient(<?= $patient['patient_id'] ?>)">
                                    <div class="patient-header">
                                        <div class="patient-name">
                                            <?= htmlspecialchars($patient['first_name'] . ' ' . 
                                                ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . 
                                                $patient['last_name']) ?>
                                        </div>
                                        <div class="patient-id"><?= htmlspecialchars($patient['username']) ?></div>
                                    </div>
                                    <div class="patient-details">
                                        <div><strong>Age:</strong> <?= htmlspecialchars($patient['age']) ?> years</div>
                                        <div><strong>Sex:</strong> <?= htmlspecialchars($patient['sex']) ?></div>
                                        <div><strong>Contact:</strong> <?= htmlspecialchars($patient['contact_number'] ?? 'N/A') ?></div>
                                        <div><strong>Barangay:</strong> <?= htmlspecialchars($patient['barangay_name'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-search">
                                <i class="fas fa-user-times fa-2x"></i>
                                <p>No patients found matching "<?= htmlspecialchars($search_query) ?>"</p>
                                <p>Try adjusting your search terms.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Consultation Form -->
            <?php if ($selected_patient): ?>
                <!-- Selected Patient Info -->
                <div class="selected-patient-info">
                    <div class="patient-info-header">
                        <i class="fas fa-user-check"></i> Selected Patient Information
                    </div>
                    <div class="patient-info-details">
                        <div class="info-item">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?= htmlspecialchars($selected_patient['first_name'] . ' ' . 
                                ($selected_patient['middle_name'] ? $selected_patient['middle_name'] . ' ' : '') . 
                                $selected_patient['last_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Patient ID</div>
                            <div class="info-value"><?= htmlspecialchars($selected_patient['username']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age / Sex</div>
                            <div class="info-value"><?= htmlspecialchars($selected_patient['age']) ?> years / <?= htmlspecialchars($selected_patient['sex']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?= date('M j, Y', strtotime($selected_patient['date_of_birth'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact</div>
                            <div class="info-value"><?= htmlspecialchars($selected_patient['contact_number'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Barangay</div>
                            <div class="info-value"><?= htmlspecialchars($selected_patient['barangay_name'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>

                <form method="POST" class="consultation-form enabled">
                    <input type="hidden" name="action" value="create_consultation">
                    <input type="hidden" name="patient_id" value="<?= $selected_patient['patient_id'] ?>">
            <?php else: ?>
                <form method="POST" class="consultation-form" id="consultationForm">
                    <input type="hidden" name="action" value="create_consultation">
                    <input type="hidden" name="patient_id" id="selectedPatientId">
            <?php endif; ?>

                    <div class="form-sections">
                        <!-- Chief Complaint and History -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-clipboard-list"></i> Chief Complaint & History
                            </h3>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="chief_complaint">Chief Complaint <span class="required">*</span></label>
                                    <textarea id="chief_complaint" name="chief_complaint" required
                                              placeholder="What brings the patient in today? (primary symptoms/concerns)"></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="history_present_illness">History of Present Illness</label>
                                    <textarea id="history_present_illness" name="history_present_illness"
                                              placeholder="Detailed description of the current condition (onset, duration, character, etc.)"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="past_medical_history">Past Medical History</label>
                                    <textarea id="past_medical_history" name="past_medical_history"
                                              placeholder="Previous illnesses, surgeries, hospitalizations"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="medications">Current Medications</label>
                                    <textarea id="medications" name="medications"
                                              placeholder="List current medications and dosages"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="allergies">Allergies</label>
                                    <textarea id="allergies" name="allergies"
                                              placeholder="Drug allergies, food allergies, environmental allergies"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="social_history">Social History</label>
                                    <textarea id="social_history" name="social_history"
                                              placeholder="Smoking, alcohol, occupation, living situation"></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="family_history">Family History</label>
                                    <textarea id="family_history" name="family_history"
                                              placeholder="Relevant family medical history"></textarea>
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
                                    <input type="number" id="systolic_bp" name="systolic_bp" min="60" max="300" placeholder="120">
                                </div>
                                <div class="form-group">
                                    <label for="diastolic_bp">Diastolic BP (mmHg)</label>
                                    <input type="number" id="diastolic_bp" name="diastolic_bp" min="40" max="200" placeholder="80">
                                </div>
                                <div class="form-group">
                                    <label for="heart_rate">Heart Rate (bpm)</label>
                                    <input type="number" id="heart_rate" name="heart_rate" min="30" max="200" placeholder="72">
                                </div>
                                <div class="form-group">
                                    <label for="respiratory_rate">Respiratory Rate (/min)</label>
                                    <input type="number" id="respiratory_rate" name="respiratory_rate" min="8" max="60" placeholder="18">
                                </div>
                                <div class="form-group">
                                    <label for="temperature">Temperature (°C)</label>
                                    <input type="number" id="temperature" name="temperature" step="0.1" min="30" max="45" placeholder="36.5">
                                </div>
                                <div class="form-group">
                                    <label for="weight">Weight (kg)</label>
                                    <input type="number" id="weight" name="weight" step="0.1" min="1" max="500" placeholder="70.0">
                                </div>
                                <div class="form-group">
                                    <label for="height">Height (cm)</label>
                                    <input type="number" id="height" name="height" step="0.1" min="50" max="250" placeholder="170.0">
                                </div>
                                <div class="form-group">
                                    <label for="oxygen_saturation">O2 Saturation (%)</label>
                                    <input type="number" id="oxygen_saturation" name="oxygen_saturation" step="0.1" min="70" max="100" placeholder="98">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="vital_signs_notes">Vital Signs Notes</label>
                                <textarea id="vital_signs_notes" name="vital_signs_notes"
                                          placeholder="Additional notes about vital signs"></textarea>
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
                                              placeholder="Overall appearance, distress level, mental status"></textarea>
                                </div>
                            </div>
                            <div class="physical-exam-grid">
                                <div class="form-group">
                                    <label for="head_neck">Head & Neck</label>
                                    <textarea id="head_neck" name="head_neck"
                                              placeholder="HEENT examination findings"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="cardiovascular">Cardiovascular</label>
                                    <textarea id="cardiovascular" name="cardiovascular"
                                              placeholder="Heart sounds, murmurs, rhythm"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="respiratory">Respiratory</label>
                                    <textarea id="respiratory" name="respiratory"
                                              placeholder="Lung sounds, breathing pattern"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="abdominal">Abdominal</label>
                                    <textarea id="abdominal" name="abdominal"
                                              placeholder="Inspection, palpation, bowel sounds"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="neurological">Neurological</label>
                                    <textarea id="neurological" name="neurological"
                                              placeholder="Mental status, reflexes, motor/sensory"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="extremities">Extremities</label>
                                    <textarea id="extremities" name="extremities"
                                              placeholder="Edema, pulses, range of motion"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="skin">Skin</label>
                                    <textarea id="skin" name="skin"
                                              placeholder="Color, lesions, rashes"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="other_findings">Other Findings</label>
                                    <textarea id="other_findings" name="other_findings"
                                              placeholder="Additional examination findings"></textarea>
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
                                              placeholder="Clinical impression and differential diagnosis"></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="diagnosis">Primary Diagnosis</label>
                                    <textarea id="diagnosis" name="diagnosis"
                                              placeholder="Primary and secondary diagnoses (ICD-10 codes if available)"></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="treatment_plan">Treatment Plan</label>
                                    <textarea id="treatment_plan" name="treatment_plan"
                                              placeholder="Medications, procedures, lifestyle modifications, referrals"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="follow_up_date">Follow-up Date</label>
                                    <input type="date" id="follow_up_date" name="follow_up_date" 
                                           min="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="follow_up_instructions">Follow-up Instructions</label>
                                    <textarea id="follow_up_instructions" name="follow_up_instructions"
                                              placeholder="When to return, what to watch for"></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label for="notes">Additional Notes</label>
                                    <textarea id="notes" name="notes"
                                              placeholder="Any additional notes or comments"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" <?= !$selected_patient ? 'disabled' : '' ?>>
                            <i class="fas fa-save"></i> Save Consultation
                        </button>
                    </div>
                </form>
        </div>
    </section>

    <script>
        // Patient selection functionality
        function selectPatient(patientId) {
            window.location.href = `consultation.php?patient_id=${patientId}`;
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('consultationForm');
            if (form) {
                const submitBtn = form.querySelector('button[type="submit"]');
                const patientIdInput = document.getElementById('selectedPatientId');
                
                // Enable form when patient is selected
                function enableForm(patientId) {
                    if (patientId) {
                        form.classList.add('enabled');
                        submitBtn.disabled = false;
                        patientIdInput.value = patientId;
                    } else {
                        form.classList.remove('enabled');
                        submitBtn.disabled = true;
                        patientIdInput.value = '';
                    }
                }

                // Patient card selection (if not pre-selected)
                const patientCards = document.querySelectorAll('.patient-card');
                patientCards.forEach(card => {
                    card.addEventListener('click', function() {
                        const patientId = this.getAttribute('onclick').match(/\d+/)[0];
                        selectPatient(patientId);
                    });
                });
            }

            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
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
                        
                        // Display BMI (you can add a display element if needed)
                        console.log('BMI:', bmi.toFixed(2));
                    }
                }
                
                weightInput.addEventListener('input', calculateBMI);
                heightInput.addEventListener('input', calculateBMI);
            }
        });
    </script>
</body>

</html>