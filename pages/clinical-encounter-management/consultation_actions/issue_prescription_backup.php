<?php
// issue_prescription.php - Prescription Creation Interface
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

// Check if role is authorized (only doctors can prescribe)
$authorized_roles = ['doctor', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    $_SESSION['snackbar_message'] = 'Only doctors are authorized to prescribe medications.';
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
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as patient_age, p.sex, p.weight
        FROM clinical_encounters e
        JOIN patients p ON e.patient_id = p.patient_id
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
    
    // Get patient's current weight from latest vitals if not in profile
    if (!$encounter['weight']) {
        $stmt = $conn->prepare("SELECT weight FROM vitals WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1");
        $stmt->bind_param('i', $encounter['patient_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $vitals = $result->fetch_assoc();
        $encounter['weight'] = $vitals['weight'] ?? null;
    }
    
} catch (Exception $e) {
    header('Location: ../index.php');
    exit();
}

// Get existing prescriptions for this encounter
$existing_prescriptions = [];
try {
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
    $existing_prescriptions = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for existing prescriptions
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'issue_prescription') {
        try {
            $conn->begin_transaction();
            
            // Get form data
            $medication_name = trim($_POST['medication_name'] ?? '');
            $generic_name = trim($_POST['generic_name'] ?? '');
            $dosage = trim($_POST['dosage'] ?? '');
            $dosage_form = trim($_POST['dosage_form'] ?? '');
            $frequency = trim($_POST['frequency'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : null;
            $instructions = trim($_POST['instructions'] ?? '');
            $indications = trim($_POST['indications'] ?? '');
            $contraindications = trim($_POST['contraindications'] ?? '');
            $side_effects = trim($_POST['side_effects'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $refills_allowed = !empty($_POST['refills_allowed']) ? (int)$_POST['refills_allowed'] : 0;
            $substitute_allowed = isset($_POST['substitute_allowed']) ? 1 : 0;
            
            // Validation
            if (empty($medication_name)) {
                throw new Exception('Medication name is required.');
            }
            if (empty($dosage)) {
                throw new Exception('Dosage is required.');
            }
            if (empty($dosage_form)) {
                throw new Exception('Dosage form is required.');
            }
            if (empty($frequency)) {
                throw new Exception('Frequency is required.');
            }
            if (empty($duration)) {
                throw new Exception('Duration is required.');
            }
            if ($quantity && $quantity <= 0) {
                throw new Exception('Quantity must be a positive number.');
            }
            
            // Insert prescription
            $stmt = $conn->prepare("
                INSERT INTO prescriptions (
                    encounter_id, patient_id, medication_name, generic_name, dosage, dosage_form,
                    frequency, duration, quantity, instructions, indications, contraindications,
                    side_effects, notes, refills_allowed, substitute_allowed, prescribed_by,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->bind_param(
                'iissssssisssssiis',
                $encounter_id, $encounter['patient_id'], $medication_name, $generic_name, $dosage, $dosage_form,
                $frequency, $duration, $quantity, $instructions, $indications, $contraindications,
                $side_effects, $notes, $refills_allowed, $substitute_allowed, $employee_id
            );
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['snackbar_message'] = "Prescription issued successfully!";
            
            // Redirect back to this page to show updated list
            header("Location: issue_prescription.php?encounter_id=$encounter_id");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Common medications database
$common_medications = [
    'analgesics' => [
        ['brand' => 'Paracetamol', 'generic' => 'Paracetamol', 'forms' => ['tablet', 'syrup', 'drops']],
        ['brand' => 'Ibuprofen', 'generic' => 'Ibuprofen', 'forms' => ['tablet', 'capsule', 'syrup']],
        ['brand' => 'Aspirin', 'generic' => 'Acetylsalicylic Acid', 'forms' => ['tablet']],
        ['brand' => 'Mefenamic Acid', 'generic' => 'Mefenamic Acid', 'forms' => ['tablet', 'capsule']]
    ],
    'antibiotics' => [
        ['brand' => 'Amoxicillin', 'generic' => 'Amoxicillin', 'forms' => ['capsule', 'tablet', 'syrup', 'drops']],
        ['brand' => 'Co-amoxiclav', 'generic' => 'Amoxicillin + Clavulanic Acid', 'forms' => ['tablet', 'syrup']],
        ['brand' => 'Azithromycin', 'generic' => 'Azithromycin', 'forms' => ['tablet', 'capsule', 'syrup']],
        ['brand' => 'Ciprofloxacin', 'generic' => 'Ciprofloxacin', 'forms' => ['tablet', 'syrup']],
        ['brand' => 'Cloxacillin', 'generic' => 'Cloxacillin', 'forms' => ['capsule', 'syrup']]
    ],
    'antihypertensives' => [
        ['brand' => 'Amlodipine', 'generic' => 'Amlodipine', 'forms' => ['tablet']],
        ['brand' => 'Losartan', 'generic' => 'Losartan', 'forms' => ['tablet']],
        ['brand' => 'Enalapril', 'generic' => 'Enalapril', 'forms' => ['tablet']],
        ['brand' => 'Nifedipine', 'generic' => 'Nifedipine', 'forms' => ['tablet']]
    ],
    'antidiabetics' => [
        ['brand' => 'Metformin', 'generic' => 'Metformin', 'forms' => ['tablet']],
        ['brand' => 'Glibenclamide', 'generic' => 'Glibenclamide', 'forms' => ['tablet']],
        ['brand' => 'Insulin', 'generic' => 'Human Insulin', 'forms' => ['injection']]
    ],
    'respiratory' => [
        ['brand' => 'Salbutamol', 'generic' => 'Salbutamol', 'forms' => ['inhaler', 'tablet', 'syrup']],
        ['brand' => 'Loratadine', 'generic' => 'Loratadine', 'forms' => ['tablet', 'syrup']],
        ['brand' => 'Cetirizine', 'generic' => 'Cetirizine', 'forms' => ['tablet', 'syrup', 'drops']]
    ],
    'gastrointestinal' => [
        ['brand' => 'Omeprazole', 'generic' => 'Omeprazole', 'forms' => ['capsule']],
        ['brand' => 'Ranitidine', 'generic' => 'Ranitidine', 'forms' => ['tablet', 'syrup']],
        ['brand' => 'Loperamide', 'generic' => 'Loperamide', 'forms' => ['capsule', 'tablet']]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Issue Prescription - <?= htmlspecialchars($encounter['patient_first_name'] . ' ' . $encounter['patient_last_name']) ?> | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .encounter-info-card {
            background: #e8f5e8;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 2rem;
        }

        .encounter-info-header {
            font-weight: 600;
            color: #28a745;
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

        .existing-prescriptions-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #28a745;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .prescription-card {
            background: #f8fff8;
            border: 1px solid #d4edda;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .medication-name {
            font-weight: 600;
            color: #28a745;
            font-size: 1.1rem;
        }

        .prescription-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background: #d4edda;
            color: #155724;
        }

        .prescription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .medication-selection {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            margin-bottom: 1rem;
        }

        .medication-categories {
            display: grid;
            gap: 1rem;
        }

        .medication-category {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        .category-header {
            background: #e9ecef;
            padding: 1rem;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-content {
            display: none;
            padding: 1rem;
            background: white;
        }

        .category-content.active {
            display: block;
        }

        .medication-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 0.5rem;
        }

        .medication-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .medication-option:hover {
            background: #f8f9fa;
            border-color: #0077b6;
        }

        .medication-option input[type="radio"] {
            margin: 0;
        }

        .medication-info {
            flex: 1;
        }

        .brand-name {
            font-weight: 600;
            color: #0077b6;
        }

        .generic-name {
            font-size: 0.85rem;
            color: #6c757d;
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
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
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

        .dosage-calculator {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin: 1rem 0;
        }

        .calculator-title {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 0.5rem;
        }

        .calculator-info {
            font-size: 0.9rem;
            color: #1565c0;
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

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
            background: linear-gradient(135deg, #28a745, #20c997);
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

        @media (max-width: 768px) {
            .encounter-info-details {
                grid-template-columns: 1fr;
            }

            .prescription-details {
                grid-template-columns: 1fr;
            }

            .form-grid.two-column,
            .form-grid.three-column {
                grid-template-columns: 1fr;
            }

            .medication-options {
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
        'title' => 'Issue Prescription',
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
                <strong>Prescription Guidelines:</strong>
                <ul>
                    <li>Verify patient allergies and contraindications before prescribing.</li>
                    <li>Use generic names when possible for cost-effectiveness.</li>
                    <li>Provide clear dosing instructions and duration of treatment.</li>
                    <li>Consider patient's weight, age, and kidney function for dosing.</li>
                    <li>Include indication for use and potential side effects.</li>
                    <li>Review drug interactions with current medications.</li>
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

            <!-- Show allergy warning if patient has documented allergies -->
            <?php if (!empty($encounter['allergies'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Patient Allergies:</strong> <?= htmlspecialchars($encounter['allergies']) ?>
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
                        <div class="info-label">Weight</div>
                        <div class="info-value"><?= $encounter['weight'] ? htmlspecialchars($encounter['weight']) . ' kg' : 'Not recorded' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Encounter Date</div>
                        <div class="info-value"><?= date('M j, Y g:i A', strtotime($encounter['created_at'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Current Medications</div>
                        <div class="info-value"><?= !empty($encounter['medications']) ? htmlspecialchars(substr($encounter['medications'], 0, 50)) . '...' : 'None recorded' ?></div>
                    </div>
                </div>
            </div>

            <!-- Existing Prescriptions -->
            <div class="existing-prescriptions-section">
                <h3 class="section-title">
                    <i class="fas fa-pills"></i> Current Prescriptions (<?= count($existing_prescriptions) ?>)
                </h3>
                <?php if (!empty($existing_prescriptions)): ?>
                    <?php foreach ($existing_prescriptions as $prescription): ?>
                        <div class="prescription-card">
                            <div class="prescription-header">
                                <div class="medication-name"><?= htmlspecialchars($prescription['medication_name']) ?></div>
                                <div class="prescription-status"><?= htmlspecialchars(ucwords($prescription['status'])) ?></div>
                            </div>
                            <div class="prescription-details">
                                <div class="info-item">
                                    <div class="info-label">Generic Name</div>
                                    <div class="info-value"><?= htmlspecialchars($prescription['generic_name']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Dosage & Form</div>
                                    <div class="info-value"><?= htmlspecialchars($prescription['dosage']) ?> <?= htmlspecialchars($prescription['dosage_form']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Frequency</div>
                                    <div class="info-value"><?= htmlspecialchars($prescription['frequency']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Duration</div>
                                    <div class="info-value"><?= htmlspecialchars($prescription['duration']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Quantity</div>
                                    <div class="info-value"><?= htmlspecialchars($prescription['quantity'] ?? 'Not specified') ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Prescribed By</div>
                                    <div class="info-value">Dr. <?= htmlspecialchars($prescription['prescriber_first_name'] . ' ' . $prescription['prescriber_last_name']) ?></div>
                                </div>
                            </div>
                            <?php if (!empty($prescription['instructions'])): ?>
                            <div class="info-item" style="margin-top: 1rem;">
                                <div class="info-label">Instructions</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($prescription['instructions'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-pills fa-2x"></i>
                        <p>No prescriptions have been issued for this consultation yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Issue New Prescription Form -->
            <form method="POST" class="prescription-form">
                <input type="hidden" name="action" value="issue_prescription">

                <div class="form-sections">
                    <!-- Medication Selection -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-plus-circle"></i> Issue New Prescription
                        </h3>
                        
                        <div class="form-group">
                            <label>Select Medication <span class="required">*</span></label>
                            <div class="medication-selection">
                                <div class="medication-categories">
                                    <?php foreach ($common_medications as $category => $medications): ?>
                                        <div class="medication-category">
                                            <div class="category-header" onclick="toggleCategory('<?= $category ?>')">
                                                <span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $category))) ?></span>
                                                <i class="fas fa-chevron-down" id="icon-<?= $category ?>"></i>
                                            </div>
                                            <div class="category-content" id="content-<?= $category ?>">
                                                <div class="medication-options">
                                                    <?php foreach ($medications as $medication): ?>
                                                        <div class="medication-option" onclick="selectMedication('<?= htmlspecialchars($medication['brand']) ?>', '<?= htmlspecialchars($medication['generic']) ?>', <?= json_encode($medication['forms']) ?>)">
                                                            <input type="radio" name="medication_name" value="<?= htmlspecialchars($medication['brand']) ?>" id="med-<?= md5($medication['brand']) ?>">
                                                            <div class="medication-info">
                                                                <div class="brand-name"><?= htmlspecialchars($medication['brand']) ?></div>
                                                                <div class="generic-name"><?= htmlspecialchars($medication['generic']) ?></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Entry Option -->
                        <div class="form-grid two-column">
                            <div class="form-group">
                                <label for="medication_name_manual">Or Enter Medication Name Manually <span class="required">*</span></label>
                                <input type="text" id="medication_name_manual" name="medication_name" 
                                       placeholder="Enter brand name or generic name">
                            </div>
                            <div class="form-group">
                                <label for="generic_name">Generic Name</label>
                                <input type="text" id="generic_name" name="generic_name" 
                                       placeholder="Enter generic name if different">
                            </div>
                        </div>
                    </div>

                    <!-- Dosage Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-pills"></i> Dosage Information
                        </h3>
                        
                        <div class="form-grid three-column">
                            <div class="form-group">
                                <label for="dosage">Dosage Strength <span class="required">*</span></label>
                                <input type="text" id="dosage" name="dosage" required
                                       placeholder="e.g., 500mg, 250mg/5ml">
                            </div>
                            <div class="form-group">
                                <label for="dosage_form">Dosage Form <span class="required">*</span></label>
                                <select id="dosage_form" name="dosage_form" required>
                                    <option value="">Select form...</option>
                                    <option value="tablet">Tablet</option>
                                    <option value="capsule">Capsule</option>
                                    <option value="syrup">Syrup</option>
                                    <option value="drops">Drops</option>
                                    <option value="injection">Injection</option>
                                    <option value="cream">Cream</option>
                                    <option value="ointment">Ointment</option>
                                    <option value="inhaler">Inhaler</option>
                                    <option value="patch">Patch</option>
                                    <option value="suppository">Suppository</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" min="1"
                                       placeholder="Number of units">
                            </div>
                        </div>

                        <div class="form-grid two-column">
                            <div class="form-group">
                                <label for="frequency">Frequency <span class="required">*</span></label>
                                <select id="frequency" name="frequency" required>
                                    <option value="">Select frequency...</option>
                                    <option value="Once daily">Once daily</option>
                                    <option value="Twice daily">Twice daily</option>
                                    <option value="Three times daily">Three times daily</option>
                                    <option value="Four times daily">Four times daily</option>
                                    <option value="Every 4 hours">Every 4 hours</option>
                                    <option value="Every 6 hours">Every 6 hours</option>
                                    <option value="Every 8 hours">Every 8 hours</option>
                                    <option value="Every 12 hours">Every 12 hours</option>
                                    <option value="As needed">As needed (PRN)</option>
                                    <option value="Before meals">Before meals</option>
                                    <option value="After meals">After meals</option>
                                    <option value="At bedtime">At bedtime</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="duration">Duration <span class="required">*</span></label>
                                <select id="duration" name="duration" required>
                                    <option value="">Select duration...</option>
                                    <option value="3 days">3 days</option>
                                    <option value="5 days">5 days</option>
                                    <option value="7 days">7 days (1 week)</option>
                                    <option value="10 days">10 days</option>
                                    <option value="14 days">14 days (2 weeks)</option>
                                    <option value="21 days">21 days (3 weeks)</option>
                                    <option value="30 days">30 days (1 month)</option>
                                    <option value="60 days">60 days (2 months)</option>
                                    <option value="90 days">90 days (3 months)</option>
                                    <option value="As needed">As needed</option>
                                    <option value="Until finished">Until finished</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($encounter['weight']): ?>
                        <div class="dosage-calculator">
                            <div class="calculator-title"><i class="fas fa-calculator"></i> Dosage Calculator</div>
                            <div class="calculator-info">
                                Patient weight: <strong><?= htmlspecialchars($encounter['weight']) ?> kg</strong>
                                <br>For weight-based dosing, calculate appropriate dose per kg body weight.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Instructions & Clinical Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-list"></i> Instructions & Clinical Information
                        </h3>
                        
                        <div class="form-group">
                            <label for="instructions">Patient Instructions</label>
                            <textarea id="instructions" name="instructions"
                                      placeholder="How to take the medication, timing, food instructions, etc."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="indications">Indications</label>
                            <textarea id="indications" name="indications"
                                      placeholder="Condition being treated, reason for prescription"></textarea>
                        </div>

                        <div class="form-grid two-column">
                            <div class="form-group">
                                <label for="contraindications">Contraindications</label>
                                <textarea id="contraindications" name="contraindications"
                                          placeholder="When not to use this medication"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="side_effects">Side Effects</label>
                                <textarea id="side_effects" name="side_effects"
                                          placeholder="Potential side effects to watch for"></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea id="notes" name="notes"
                                      placeholder="Clinical notes, monitoring parameters, follow-up instructions"></textarea>
                        </div>

                        <div class="form-grid two-column">
                            <div class="form-group">
                                <label for="refills_allowed">Refills Allowed</label>
                                <select id="refills_allowed" name="refills_allowed">
                                    <option value="0">No refills</option>
                                    <option value="1">1 refill</option>
                                    <option value="2">2 refills</option>
                                    <option value="3">3 refills</option>
                                    <option value="5">5 refills</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="substitute_allowed" name="substitute_allowed" checked>
                                    <label for="substitute_allowed">Generic substitution allowed</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../view_consultation.php?id=<?= $encounter_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-prescription-bottle"></i> Issue Prescription
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        // Toggle category visibility
        function toggleCategory(category) {
            const content = document.getElementById(`content-${category}`);
            const icon = document.getElementById(`icon-${category}`);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.style.transform = 'rotate(0deg)';
            } else {
                // Close all other categories
                document.querySelectorAll('.category-content').forEach(c => c.classList.remove('active'));
                document.querySelectorAll('.category-header i').forEach(i => i.style.transform = 'rotate(0deg)');
                
                // Open selected category
                content.classList.add('active');
                icon.style.transform = 'rotate(180deg)';
            }
        }

        // Select medication and auto-fill details
        function selectMedication(brandName, genericName, forms) {
            // Clear manual entry
            const manualInput = document.getElementById('medication_name_manual');
            manualInput.value = '';
            
            // Set generic name
            const genericInput = document.getElementById('generic_name');
            genericInput.value = genericName;
            
            // Update dosage form options
            const dosageFormSelect = document.getElementById('dosage_form');
            if (forms && forms.length > 0) {
                // Clear current options except the placeholder
                dosageFormSelect.innerHTML = '<option value="">Select form...</option>';
                
                // Add available forms
                forms.forEach(form => {
                    const option = document.createElement('option');
                    option.value = form;
                    option.textContent = form.charAt(0).toUpperCase() + form.slice(1);
                    dosageFormSelect.appendChild(option);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Manual medication name input handling
            const manualInput = document.getElementById('medication_name_manual');
            if (manualInput) {
                manualInput.addEventListener('input', function() {
                    // Clear radio button selections when typing manually
                    document.querySelectorAll('input[name="medication_name"][type="radio"]').forEach(radio => {
                        radio.checked = false;
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

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const medicationName = document.querySelector('input[name="medication_name"]:checked')?.value || 
                                         document.getElementById('medication_name_manual').value.trim();
                    const dosage = document.getElementById('dosage').value.trim();
                    const dosageForm = document.getElementById('dosage_form').value;
                    const frequency = document.getElementById('frequency').value;
                    const duration = document.getElementById('duration').value;
                    
                    if (!medicationName) {
                        e.preventDefault();
                        alert('Please select a medication or enter medication name manually.');
                        return false;
                    }
                    
                    if (!dosage) {
                        e.preventDefault();
                        alert('Please enter the dosage strength.');
                        document.getElementById('dosage').focus();
                        return false;
                    }
                    
                    if (!dosageForm) {
                        e.preventDefault();
                        alert('Please select the dosage form.');
                        document.getElementById('dosage_form').focus();
                        return false;
                    }
                    
                    if (!frequency) {
                        e.preventDefault();
                        alert('Please select the frequency.');
                        document.getElementById('frequency').focus();
                        return false;
                    }
                    
                    if (!duration) {
                        e.preventDefault();
                        alert('Please select the duration.');
                        document.getElementById('duration').focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>

</html>