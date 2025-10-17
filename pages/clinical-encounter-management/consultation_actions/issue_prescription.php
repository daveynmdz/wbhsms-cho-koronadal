<?php
// Resolve path to root directory using realpath for consistent path format
$root_path = realpath(dirname(dirname(dirname(dirname(__FILE__)))));

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
if ($employee_stmt) {
    $employee_stmt->bind_param("i", $employee_id);
    $employee_stmt->execute();
    $employee_result = $employee_stmt->get_result();
    $employee_details = $employee_result->fetch_assoc();
} else {
    $employee_details = null;
}

if (!$employee_details) {
    header("Location: /wbhsms-cho-koronadal/pages/management/login.php");
    exit();
}

$employee_role = strtolower($employee_details['role']);
$employee_name = $employee_details['first_name'] . ' ' . $employee_details['last_name'];

// Check if role is authorized (doctors, pharmacists, and admins can prescribe)
$authorized_roles = ['doctor', 'pharmacist', 'admin'];
if (!in_array($employee_role, $authorized_roles)) {
    header("Location: ../consultation.php?visit_id=" . ($_GET['visit_id'] ?? '') . "&error=unauthorized");
    exit();
}

// Get visit_id from URL
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
if (!$visit_id) {
    header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=invalid_visit");
    exit();
}

// Initialize variables
$patient_data = null;
$visit_data = null;
$consultation_data = null;
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_prescription'])) {
    $medications = $_POST['medications'] ?? [];
    
    if (empty($medications)) {
        $error_message = "Please add at least one medication.";
    } else {
        try {
            $conn->begin_transaction();
            
            // Get consultation_id if it exists
            $consultation_id = null;
            $consult_stmt = $conn->prepare("SELECT consultation_id FROM consultations WHERE visit_id = ?");
            $consult_stmt->bind_param("i", $visit_id);
            $consult_stmt->execute();
            $consult_result = $consult_stmt->get_result();
            if ($consult_row = $consult_result->fetch_assoc()) {
                $consultation_id = $consult_row['consultation_id'];
            }
            
            // Insert prescriptions
            $insert_stmt = $conn->prepare("
                INSERT INTO prescriptions (
                    visit_id, patient_id, consultation_id, medication_name, dosage, 
                    frequency, instructions, prescribed_by, prescription_date, 
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
            ");
            
            $prescription_count = 0;
            foreach ($medications as $medication) {
                if (!empty($medication['name']) && !empty($medication['dosage']) && !empty($medication['frequency'])) {
                    $insert_stmt->bind_param(
                        "iiissssi", 
                        $visit_id, 
                        $patient_data['patient_id'], 
                        $consultation_id, 
                        $medication['name'], 
                        $medication['dosage'],
                        $medication['frequency'],
                        $medication['instructions'],
                        $employee_id
                    );
                    $insert_stmt->execute();
                    $prescription_count++;
                }
            }
            
            $conn->commit();
            
            // Redirect back to consultation with success message
            $success_param = urlencode("Prescription issued successfully: $prescription_count medication(s) prescribed");
            header("Location: ../consultation.php?visit_id=$visit_id&success=" . $success_param);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error issuing prescription: " . $e->getMessage();
        }
    }
}

// Load patient and visit data
try {
    // Get patient and visit data
    $data_stmt = $conn->prepare("
        SELECT v.*, p.first_name, p.last_name, p.username, p.date_of_birth, p.sex, p.contact_number,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               b.barangay_name, d.district_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN districts d ON b.district_id = d.district_id
        WHERE v.visit_id = ?
    ");
    $data_stmt->bind_param("i", $visit_id);
    $data_stmt->execute();
    $result = $data_stmt->get_result();
    $combined_data = $result->fetch_assoc();
    
    if (!$combined_data) {
        header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=visit_not_found");
        exit();
    }
    
    $patient_data = $combined_data;
    $visit_data = $combined_data;
    
    // Get consultation data if exists
    $consultation_stmt = $conn->prepare("SELECT * FROM consultations WHERE visit_id = ?");
    $consultation_stmt->bind_param("i", $visit_id);
    $consultation_stmt->execute();
    $consultation_result = $consultation_stmt->get_result();
    $consultation_data = $consultation_result->fetch_assoc();
    
} catch (Exception $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}

// Get existing prescriptions for this visit
$existing_prescriptions = [];
try {
    $prescription_stmt = $conn->prepare("
        SELECT p.*, e.first_name as doctor_first_name, e.last_name as doctor_last_name
        FROM prescriptions p
        LEFT JOIN employees e ON p.prescribed_by = e.employee_id
        WHERE p.visit_id = ?
        ORDER BY p.created_at DESC
    ");
    $prescription_stmt->bind_param("i", $visit_id);
    $prescription_stmt->execute();
    $prescription_result = $prescription_stmt->get_result();
    $existing_prescriptions = $prescription_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Ignore errors for existing prescriptions
}

// Include topbar for consistent navigation
require_once $root_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'topbar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Issue Prescription | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .prescription-container {
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

        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        .existing-prescriptions {
            background: #e8f5e8;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 1.5rem;
        }

        .prescription-item {
            background: white;
            border: 1px solid #d4edda;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: center;
            font-size: 0.9rem;
        }

        .prescription-item:last-child {
            margin-bottom: 0;
        }

        .medication-name {
            font-weight: 600;
            color: #28a745;
        }

        .medications-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }

        .medication-row {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .medication-row.template {
            display: none;
        }

        .row-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .row-title {
            font-weight: 600;
            color: #0077b6;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .form-grid {
            display: grid;
            gap: 1rem;
        }

        .form-grid.two-column {
            grid-template-columns: 1fr 1fr;
        }

        .form-grid.four-column {
            grid-template-columns: 2fr 1fr 1fr 2fr;
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

        .add-medication-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            transition: background-color 0.3s ease;
        }

        .add-medication-btn:hover {
            background: #218838;
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
            padding-top: 2rem;
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

        .btn-success {
            background: #28a745;
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

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        @media (max-width: 768px) {
            .form-grid.two-column,
            .form-grid.four-column {
                grid-template-columns: 1fr;
            }

            .prescription-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .patient-info-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
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
        'title' => 'Issue Prescription',
        'back_url' => '../consultation.php?visit_id=' . $visit_id,
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <div class="prescription-container">

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

            <!-- Patient Summary -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-user"></i> Patient Information
                </h3>

                <?php if ($patient_data): ?>
                    <div class="patient-info-grid">
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
                            <div class="info-label">Visit ID</div>
                            <div class="info-value">#<?= htmlspecialchars($visit_data['visit_id']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Visit Date</div>
                            <div class="info-value"><?= date('M j, Y g:i A', strtotime($visit_data['created_at'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Prescribed by</div>
                            <div class="info-value">Dr. <?= htmlspecialchars($employee_name) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Existing Prescriptions -->
            <?php if (!empty($existing_prescriptions)): ?>
                <div class="existing-prescriptions">
                    <h4><i class="fas fa-pills"></i> Current Prescriptions (<?= count($existing_prescriptions) ?>)</h4>
                    <?php foreach ($existing_prescriptions as $prescription): ?>
                        <div class="prescription-item">
                            <div class="medication-name"><?= htmlspecialchars($prescription['medication_name']) ?></div>
                            <div><strong>Dosage:</strong> <?= htmlspecialchars($prescription['dosage']) ?></div>
                            <div><strong>Frequency:</strong> <?= htmlspecialchars($prescription['frequency']) ?></div>
                            <div><strong>Instructions:</strong> <?= htmlspecialchars($prescription['instructions'] ?: 'N/A') ?></div>
                            <div><small>by Dr. <?= htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']) ?></small></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Prescription Form -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-prescription-bottle"></i> Create New Prescription
                </h3>

                <form method="POST" id="prescriptionForm">
                    <div class="medications-form">
                        <button type="button" class="add-medication-btn" onclick="addMedication()">
                            <i class="fas fa-plus"></i> Add Medication
                        </button>

                        <div id="medicationsList">
                            <!-- Medication rows will be added here dynamically -->
                        </div>

                        <!-- Template for medication row -->
                        <div class="medication-row template" id="medicationTemplate">
                            <div class="row-header">
                                <span class="row-title">Medication #<span class="medication-number">1</span></span>
                                <button type="button" class="remove-btn" onclick="removeMedication(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <div class="form-grid four-column">
                                <div class="form-group">
                                    <label>Medication Name <span class="required">*</span></label>
                                    <input type="text" name="medications[][name]" 
                                           placeholder="Enter medication name" required>
                                </div>
                                <div class="form-group">
                                    <label>Dosage <span class="required">*</span></label>
                                    <input type="text" name="medications[][dosage]" 
                                           placeholder="e.g., 500mg" required>
                                </div>
                                <div class="form-group">
                                    <label>Frequency <span class="required">*</span></label>
                                    <select name="medications[][frequency]" required>
                                        <option value="">Select frequency</option>
                                        <option value="Once daily">Once daily</option>
                                        <option value="Twice daily">Twice daily</option>
                                        <option value="Three times daily">Three times daily</option>
                                        <option value="Four times daily">Four times daily</option>
                                        <option value="Every 4 hours">Every 4 hours</option>
                                        <option value="Every 6 hours">Every 6 hours</option>
                                        <option value="Every 8 hours">Every 8 hours</option>
                                        <option value="As needed">As needed</option>
                                        <option value="Before meals">Before meals</option>
                                        <option value="After meals">After meals</option>
                                        <option value="At bedtime">At bedtime</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Instructions</label>
                                    <textarea name="medications[][instructions]" 
                                              placeholder="Special instructions for taking this medication"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="../consultation.php?visit_id=<?= $visit_id ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Consultation
                        </a>
                        <button type="submit" name="issue_prescription" class="btn btn-success" id="submitBtn" disabled>
                            <i class="fas fa-prescription-bottle"></i> Save Prescription
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>

    <script>
        let medicationCount = 0;

        function addMedication() {
            medicationCount++;
            const template = document.getElementById('medicationTemplate');
            const clone = template.cloneNode(true);
            
            // Remove template class and give unique ID
            clone.classList.remove('template');
            clone.id = `medication-${medicationCount}`;
            
            // Update medication number
            clone.querySelector('.medication-number').textContent = medicationCount;
            
            // Update input names to include unique index
            const inputs = clone.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.name) {
                    input.name = input.name.replace('[]', `[${medicationCount-1}]`);
                }
            });
            
            // Add to medications list
            document.getElementById('medicationsList').appendChild(clone);
            
            // Enable submit button
            updateSubmitButton();
        }

        function removeMedication(button) {
            const medicationRow = button.closest('.medication-row');
            medicationRow.remove();
            
            // Renumber remaining medications
            const medications = document.querySelectorAll('.medication-row:not(.template)');
            medications.forEach((med, index) => {
                med.querySelector('.medication-number').textContent = index + 1;
                
                // Update input names
                const inputs = med.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
                    }
                });
            });
            
            medicationCount = medications.length;
            updateSubmitButton();
        }

        function updateSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            const medicationRows = document.querySelectorAll('.medication-row:not(.template)');
            submitBtn.disabled = medicationRows.length === 0;
        }

        // Add first medication on page load
        document.addEventListener('DOMContentLoaded', function() {
            addMedication();

            // Auto-resize textareas
            document.addEventListener('input', function(e) {
                if (e.target.tagName === 'TEXTAREA') {
                    e.target.style.height = 'auto';
                    e.target.style.height = (e.target.scrollHeight) + 'px';
                }
            });

            // Form validation
            document.getElementById('prescriptionForm').addEventListener('submit', function(e) {
                const medicationRows = document.querySelectorAll('.medication-row:not(.template)');
                if (medicationRows.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one medication.');
                    return false;
                }

                // Validate each medication row
                for (let row of medicationRows) {
                    const name = row.querySelector('input[name*="[name]"]').value.trim();
                    const dosage = row.querySelector('input[name*="[dosage]"]').value.trim();
                    const frequency = row.querySelector('select[name*="[frequency]"]').value;

                    if (!name || !dosage || !frequency) {
                        e.preventDefault();
                        alert('Please fill in all required fields for each medication.');
                        return false;
                    }
                }
            });

            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>

</html>