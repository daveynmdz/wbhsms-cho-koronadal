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

// Check if user has permission to edit consultations (only doctors and admins)
$authorized_roles = ['doctor', 'admin'];
if (!in_array($employee_role, $authorized_roles)) {
    header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=unauthorized_edit");
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
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_consultation'])) {
    $chief_complaint = trim($_POST['chief_complaint'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment_plan = trim($_POST['treatment_plan'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $consultation_status = $_POST['consultation_status'] ?? 'pending';
    
    // Validation
    if (empty($chief_complaint) || empty($diagnosis)) {
        $error_message = "Chief Complaint and Diagnosis are required.";
    } else {
        try {
            // Update consultation
            $update_stmt = $conn->prepare("
                UPDATE consultations 
                SET chief_complaint = ?, diagnosis = ?, treatment_plan = ?, remarks = ?, 
                    consultation_status = ?, updated_at = NOW()
                WHERE consultation_id = ?
            ");
            if ($update_stmt) {
                $update_stmt->bind_param("sssssi", $chief_complaint, $diagnosis, $treatment_plan, $remarks, $consultation_status, $consultation_id);
                
                if ($update_stmt->execute()) {
                $success_message = "Consultation updated successfully.";
                
                // Redirect to view consultation with success message
                $success_param = urlencode("Consultation updated successfully by " . $employee_name);
                header("Location: view_consultation.php?id=$consultation_id&success=" . $success_param);
                exit();
            } else {
                $error_message = "Error updating consultation. Please try again.";
            }
            } else {
                $error_message = "Database error occurred.";
            }
        } catch (Exception $e) {
            $error_message = "Error updating consultation: " . $e->getMessage();
        }
    }
}

// Load consultation data
try {
    // Get consultation with patient and visit information
    $consultation_stmt = $conn->prepare("
        SELECT c.*, p.first_name, p.last_name, p.username, p.date_of_birth, p.sex, p.contact_number,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               b.barangay_name, d.district_name,
               v.visit_type, v.visit_purpose, v.created_at as visit_date,
               doc.first_name as doctor_first_name, doc.last_name as doctor_last_name
        FROM consultations c
        JOIN visits v ON c.visit_id = v.visit_id
        JOIN patients p ON c.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN districts d ON b.district_id = d.district_id
        LEFT JOIN employees doc ON c.attending_employee_id = doc.employee_id
        WHERE c.consultation_id = ?
    ");
    if ($consultation_stmt) {
        $consultation_stmt->bind_param("i", $consultation_id);
        $consultation_stmt->execute();
        $result = $consultation_stmt->get_result();
        $consultation_data = $result->fetch_assoc();
    } else {
        $consultation_data = null;
    }
    
    if (!$consultation_data) {
        header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=consultation_not_found");
        exit();
    }
    
    // Check if doctor can only edit their own consultations (unless admin)
    if ($employee_role === 'doctor' && $consultation_data['attending_employee_id'] != $employee_id) {
        header("Location: view_consultation.php?id=$consultation_id&error=edit_permission_denied");
        exit();
    }
    
    $patient_data = $consultation_data;
    $visit_data = $consultation_data;
    
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
    <title>Edit Consultation | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .edit-container {
            max-width: 1000px;
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
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        .form-grid {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
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
            min-height: 120px;
        }

        .form-group.large-textarea textarea {
            min-height: 150px;
        }

        .status-select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1em;
            appearance: none;
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

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .edit-notice {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            margin-top: 2rem;
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

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .char-counter {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
            margin-top: 0.25rem;
        }

        .char-counter.warning {
            color: #856404;
        }

        .char-counter.danger {
            color: #dc3545;
        }

        .form-help {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .info-grid {
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
        'title' => 'Edit Consultation',
        'back_url' => 'view_consultation.php?id=' . $consultation_id,
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <div class="edit-container">

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

            <div class="edit-notice">
                <i class="fas fa-edit"></i>
                <strong>Editing Mode:</strong> You are editing a consultation record. Changes will be logged and timestamped. 
                Role: <?= ucfirst(str_replace('_', ' ', $employee_role)) ?>
            </div>

            <?php if ($consultation_data): ?>

                <!-- Patient Information (Read-Only) -->
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
                            <div class="info-label">Visit Date</div>
                            <div class="info-value"><?= date('M j, Y g:i A', strtotime($visit_data['visit_date'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Consultation Date</div>
                            <div class="info-value"><?= date('M j, Y g:i A', strtotime($consultation_data['consultation_date'])) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Editable Consultation Form -->
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-stethoscope"></i> Edit Consultation Details
                    </h3>

                    <form method="POST" id="consultationForm">
                        <div class="form-grid">
                            
                            <div class="form-group large-textarea">
                                <label for="chief_complaint">Chief Complaint <span class="required">*</span></label>
                                <textarea id="chief_complaint" 
                                          name="chief_complaint" 
                                          required 
                                          maxlength="1000"
                                          placeholder="Describe the patient's primary concern or reason for the visit..."><?= htmlspecialchars($consultation_data['chief_complaint'] ?? '') ?></textarea>
                                <div class="char-counter" id="cc-counter">
                                    <span id="cc-count"><?= strlen($consultation_data['chief_complaint'] ?? '') ?></span>/1000 characters
                                </div>
                                <div class="form-help">Summarize the patient's main symptoms, concerns, or reason for seeking care.</div>
                            </div>

                            <div class="form-group large-textarea">
                                <label for="diagnosis">Diagnosis <span class="required">*</span></label>
                                <textarea id="diagnosis" 
                                          name="diagnosis" 
                                          required 
                                          maxlength="1000"
                                          placeholder="Enter the clinical diagnosis based on examination and assessment..."><?= htmlspecialchars($consultation_data['diagnosis'] ?? '') ?></textarea>
                                <div class="char-counter" id="diag-counter">
                                    <span id="diag-count"><?= strlen($consultation_data['diagnosis'] ?? '') ?></span>/1000 characters
                                </div>
                                <div class="form-help">Provide the medical diagnosis or clinical impression based on your assessment.</div>
                            </div>

                            <div class="form-group large-textarea">
                                <label for="treatment_plan">Treatment Plan</label>
                                <textarea id="treatment_plan" 
                                          name="treatment_plan" 
                                          maxlength="2000"
                                          placeholder="Outline the recommended treatment approach, medications, procedures, or interventions..."><?= htmlspecialchars($consultation_data['treatment_plan'] ?? '') ?></textarea>
                                <div class="char-counter" id="treat-counter">
                                    <span id="treat-count"><?= strlen($consultation_data['treatment_plan'] ?? '') ?></span>/2000 characters
                                </div>
                                <div class="form-help">Detail the recommended treatment approach, including medications, procedures, lifestyle recommendations, etc.</div>
                            </div>

                            <div class="form-group">
                                <label for="remarks">Additional Remarks</label>
                                <textarea id="remarks" 
                                          name="remarks" 
                                          maxlength="1000"
                                          placeholder="Any additional observations, notes, or instructions..."><?= htmlspecialchars($consultation_data['remarks'] ?? '') ?></textarea>
                                <div class="char-counter" id="remarks-counter">
                                    <span id="remarks-count"><?= strlen($consultation_data['remarks'] ?? '') ?></span>/1000 characters
                                </div>
                                <div class="form-help">Include any additional observations, follow-up instructions, or notes relevant to the case.</div>
                            </div>

                            <div class="form-group">
                                <label for="consultation_status">Consultation Status</label>
                                <select id="consultation_status" name="consultation_status" class="status-select">
                                    <option value="pending" <?= ($consultation_data['consultation_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in-progress" <?= ($consultation_data['consultation_status'] ?? '') === 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= ($consultation_data['consultation_status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="follow-up scheduled" <?= ($consultation_data['consultation_status'] ?? '') === 'follow-up scheduled' ? 'selected' : '' ?>>Follow-up Scheduled</option>
                                </select>
                                <div class="form-help">Update the current status of this consultation.</div>
                            </div>

                        </div>

                        <div class="form-actions">
                            <a href="view_consultation.php?id=<?= $consultation_id ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="update_consultation" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="section-card">
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-file-medical fa-3x" style="color: #6c757d; margin-bottom: 1rem;"></i>
                        <h3>Consultation Not Found</h3>
                        <p>The requested consultation could not be found or you don't have permission to edit it.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Character counters
            const textareas = [
                { id: 'chief_complaint', counterId: 'cc-count', counterId_wrapper: 'cc-counter', maxLength: 1000 },
                { id: 'diagnosis', counterId: 'diag-count', counterId_wrapper: 'diag-counter', maxLength: 1000 },
                { id: 'treatment_plan', counterId: 'treat-count', counterId_wrapper: 'treat-counter', maxLength: 2000 },
                { id: 'remarks', counterId: 'remarks-count', counterId_wrapper: 'remarks-counter', maxLength: 1000 }
            ];

            textareas.forEach(function(textarea) {
                const element = document.getElementById(textarea.id);
                const counter = document.getElementById(textarea.counterId);
                const wrapper = document.getElementById(textarea.counterId_wrapper);
                
                if (element && counter && wrapper) {
                    element.addEventListener('input', function() {
                        const length = this.value.length;
                        counter.textContent = length;
                        
                        // Update counter styling based on length
                        wrapper.classList.remove('warning', 'danger');
                        if (length > textarea.maxLength * 0.9) {
                            wrapper.classList.add('danger');
                        } else if (length > textarea.maxLength * 0.8) {
                            wrapper.classList.add('warning');
                        }
                        
                        // Auto-resize textarea
                        this.style.height = 'auto';
                        this.style.height = (this.scrollHeight) + 'px';
                    });
                }
            });

            // Form validation
            document.getElementById('consultationForm').addEventListener('submit', function(e) {
                const chiefComplaint = document.getElementById('chief_complaint').value.trim();
                const diagnosis = document.getElementById('diagnosis').value.trim();
                
                if (!chiefComplaint) {
                    e.preventDefault();
                    alert('Chief Complaint is required.');
                    document.getElementById('chief_complaint').focus();
                    return false;
                }
                
                if (!diagnosis) {
                    e.preventDefault();
                    alert('Diagnosis is required.');
                    document.getElementById('diagnosis').focus();
                    return false;
                }
                
                // Confirm changes
                if (!confirm('Are you sure you want to save these changes to the consultation?')) {
                    e.preventDefault();
                    return false;
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

            // Initial auto-resize for all textareas
            textareas.forEach(function(textarea) {
                const element = document.getElementById(textarea.id);
                if (element) {
                    element.style.height = 'auto';
                    element.style.height = (element.scrollHeight) + 'px';
                }
            });
        });
    </script>
</body>

</html>