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

// Get visit_id from URL
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
if (!$visit_id) {
    // Redirect to clinical encounter management with a helpful message
    header("Location: index.php?error=visit_required");
    exit();
}

// Initialize variables
$patient_data = null;
$visit_data = null;
$vitals_data = null;
$consultation_data = null;
$success_message = '';
$error_message = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Save Vitals
    if (isset($_POST['save_vitals']) && in_array($employee_role, ['nurse', 'doctor'])) {
        $blood_pressure = trim($_POST['blood_pressure'] ?? '');
        $heart_rate = isset($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : null;
        $temperature = isset($_POST['temperature']) ? (float)$_POST['temperature'] : null;
        $respiratory_rate = isset($_POST['respiratory_rate']) ? (int)$_POST['respiratory_rate'] : null;
        $height = isset($_POST['height']) ? (float)$_POST['height'] : null;
        $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : null;
        
        try {
            // Check if vitals already exist
            $check_stmt = $conn->prepare("SELECT vital_id FROM vitals WHERE visit_id = ?");
            if ($check_stmt) {
                $check_stmt->bind_param("i", $visit_id);
                $check_stmt->execute();
                $existing_vitals = $check_stmt->get_result()->fetch_assoc();
                
                if ($existing_vitals) {
                    // Update existing vitals
                    $update_stmt = $conn->prepare("UPDATE vitals SET blood_pressure = ?, heart_rate = ?, temperature = ?, respiratory_rate = ?, height = ?, weight = ?, updated_by = ?, updated_at = NOW() WHERE visit_id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("siidddii", $blood_pressure, $heart_rate, $temperature, $respiratory_rate, $height, $weight, $employee_id, $visit_id);
                        $update_stmt->execute();
                    }
                } else {
                    // Insert new vitals
                    $insert_stmt = $conn->prepare("INSERT INTO vitals (visit_id, blood_pressure, heart_rate, temperature, respiratory_rate, height, weight, taken_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("isiidddi", $visit_id, $blood_pressure, $heart_rate, $temperature, $respiratory_rate, $height, $weight, $employee_id);
                        $insert_stmt->execute();
                    }
                }
            }
            
            $success_message = "Vitals saved successfully.";
        } catch (Exception $e) {
            $error_message = "Error saving vitals: " . $e->getMessage();
        }
    }
    
    // Save Consultation
    if (isset($_POST['save_consultation']) && $employee_role === 'doctor') {
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
                // Get patient_id from visit
                $visit_stmt = $conn->prepare("SELECT patient_id FROM visits WHERE visit_id = ?");
                if ($visit_stmt) {
                    $visit_stmt->bind_param("i", $visit_id);
                    $visit_stmt->execute();
                    $visit_result = $visit_stmt->get_result()->fetch_assoc();
                    
                    if (!$visit_result) {
                        $error_message = "Invalid visit.";
                    } else {
                        $patient_id = $visit_result['patient_id'];
                        
                        // Check if consultation already exists
                        $check_stmt = $conn->prepare("SELECT consultation_id FROM consultations WHERE visit_id = ?");
                        if ($check_stmt) {
                            $check_stmt->bind_param("i", $visit_id);
                            $check_stmt->execute();
                            $existing_consultation = $check_stmt->get_result()->fetch_assoc();
                            
                            if ($existing_consultation) {
                                // Update existing consultation
                                $update_stmt = $conn->prepare("UPDATE consultations SET chief_complaint = ?, diagnosis = ?, treatment_plan = ?, remarks = ?, consultation_status = ?, attending_employee_id = ?, updated_at = NOW() WHERE visit_id = ?");
                                if ($update_stmt) {
                                    $update_stmt->bind_param("sssssii", $chief_complaint, $diagnosis, $treatment_plan, $remarks, $consultation_status, $employee_id, $visit_id);
                                    $update_stmt->execute();
                                }
                            } else {
                                // Insert new consultation
                                $insert_stmt = $conn->prepare("INSERT INTO consultations (visit_id, patient_id, attending_employee_id, chief_complaint, diagnosis, treatment_plan, remarks, consultation_status, consultation_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())");
                                if ($insert_stmt) {
                                    $insert_stmt->bind_param("iiisssss", $visit_id, $patient_id, $employee_id, $chief_complaint, $diagnosis, $treatment_plan, $remarks, $consultation_status);
                                    $insert_stmt->execute();
                                }
                            }
                        }
                    }
                } else {
                    $error_message = "Database error occurred.";
                }
                
                $success_message = "Consultation saved successfully.";
            } catch (Exception $e) {
                $error_message = "Error saving consultation: " . $e->getMessage();
            }
        }
    }
}

// Load patient, visit, vitals, and consultation data
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
    if ($data_stmt) {
        $data_stmt->bind_param("i", $visit_id);
        $data_stmt->execute();
        $result = $data_stmt->get_result();
        $combined_data = $result->fetch_assoc();
    } else {
        $combined_data = null;
    }
    
    if (!$combined_data) {
        header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=visit_not_found");
        exit();
    }
    
    $patient_data = $combined_data;
    $visit_data = $combined_data;
    
    // Get vitals data
    $vitals_stmt = $conn->prepare("SELECT * FROM vitals WHERE visit_id = ?");
    if ($vitals_stmt) {
        $vitals_stmt->bind_param("i", $visit_id);
        $vitals_stmt->execute();
        $vitals_result = $vitals_stmt->get_result();
        $vitals_data = $vitals_result->fetch_assoc();
    } else {
        $vitals_data = null;
    }
    
    // Get consultation data
    $consultation_stmt = $conn->prepare("
        SELECT c.*, 
               creator.first_name as created_by_name, creator.last_name as created_by_surname,
               updater.first_name as updated_by_name, updater.last_name as updated_by_surname
        FROM consultations c
        LEFT JOIN employees creator ON c.attending_employee_id = creator.employee_id
        LEFT JOIN employees updater ON c.attending_employee_id = updater.employee_id
        WHERE c.visit_id = ?
    ");
    if ($consultation_stmt) {
        $consultation_stmt->bind_param("i", $visit_id);
        $consultation_stmt->execute();
        $consultation_result = $consultation_stmt->get_result();
        $consultation_data = $consultation_result->fetch_assoc();
    } else {
        $consultation_data = null;
    }
    
} catch (Exception $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}

// Role-based permissions
$can_edit_vitals = in_array($employee_role, ['nurse', 'doctor', 'admin']);
$can_edit_consultation = in_array($employee_role, ['doctor', 'admin']);
$can_order_lab = in_array($employee_role, ['doctor', 'admin']);
$can_prescribe = in_array($employee_role, ['doctor', 'admin']);
$can_order_followup = in_array($employee_role, ['doctor', 'admin']);
$is_read_only = in_array($employee_role, ['records_officer', 'bhw', 'dho']);

// For BHW and DHO, check if they have access to this patient based on location
$has_patient_access = true;
if ($employee_role === 'bhw' || $employee_role === 'dho') {
    try {
        // Get employee's assigned barangay/district
        $location_stmt = $conn->prepare("
            SELECT e.assigned_barangay_id, e.assigned_district_id, p.barangay_id, b.district_id
            FROM employees e, patients p
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
            WHERE e.employee_id = ? AND p.patient_id = (SELECT patient_id FROM visits WHERE visit_id = ?)
        ");
        if ($location_stmt) {
            $location_stmt->bind_param("ii", $employee_id, $visit_id);
            $location_stmt->execute();
            $location_result = $location_stmt->get_result()->fetch_assoc();
        } else {
            $location_result = null;
        }
        
        if ($location_result) {
            if ($employee_role === 'bhw') {
                // BHW can only access patients from their assigned barangay
                $has_patient_access = ($location_result['assigned_barangay_id'] == $location_result['barangay_id']);
            } elseif ($employee_role === 'dho') {
                // DHO can access patients from their assigned district
                $has_patient_access = ($location_result['assigned_district_id'] == $location_result['district_id']);
            }
        }
    } catch (Exception $e) {
        $has_patient_access = false;
    }
}

// If user doesn't have access to this patient, redirect with error
if (!$has_patient_access) {
    header("Location: /wbhsms-cho-koronadal/pages/clinical-encounter-management/index.php?error=access_denied");
    exit();
}
$can_view_profile = true; // All roles can view profiles

// Determine profile viewer URL based on role
$profile_viewer_url = '';
switch($employee_role) {
    case 'admin':
        $profile_viewer_url = "/wbhsms-cho-koronadal/pages/management/admin/patient-records/view_patient.php?patient_id=" . $patient_data['patient_id'];
        break;
    case 'doctor':
    case 'nurse':
        $profile_viewer_url = "/wbhsms-cho-koronadal/pages/management/clinical/patient_profile.php?patient_id=" . $patient_data['patient_id'];
        break;
    default:
        $profile_viewer_url = "/wbhsms-cho-koronadal/pages/management/patient_profile.php?patient_id=" . $patient_data['patient_id'];
        break;
}

// Include topbar for consistent navigation
require_once $root_path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'topbar.php';
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

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0077b6;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-actions {
            display: flex;
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

        .form-group input:disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
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

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
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
            transform: translateY(-1px);
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .disabled-section {
            opacity: 0.6;
            pointer-events: none;
            background: #f8f9fa;
        }

        .role-warning {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #ffeaa7;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .audit-info {
            background: #e8f4f8;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #bee5eb;
            font-size: 0.9rem;
        }

        .audit-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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

            .patient-info-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .action-buttons {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php 
    // Render topbar
    renderTopbar([
        'title' => 'Clinical Consultation',
        'back_url' => 'index.php',
        'user_type' => 'employee'
    ]);
    ?>

    <section class="homepage">
        <div class="consultation-container">

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

            <!-- Section 1: Patient Summary -->
            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Patient Summary
                    </h3>
                    <?php if ($can_view_profile): ?>
                        <a href="<?= $profile_viewer_url ?>" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i> View Full Profile
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($patient_data): ?>
                    <div class="patient-info-grid">
                        <div class="info-item">
                            <div class="info-label">Patient Name</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['age']) ?> years</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Sex</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['sex']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['barangay_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">District</div>
                            <div class="info-value"><?= htmlspecialchars($patient_data['district_name'] ?? 'N/A') ?></div>
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
                            <div class="info-label">Visit Type</div>
                            <div class="info-value"><?= htmlspecialchars(ucfirst($visit_data['visit_type'] ?? 'N/A')) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 2: Patient Vitals (Triage) -->
            <div class="section-card <?= !$can_edit_vitals ? 'disabled-section' : '' ?>">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-heartbeat"></i> Patient Vitals (Triage)
                    </h3>
                    <?php if ($can_edit_vitals): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="save_vitals" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Vitals
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($is_read_only): ?>
                    <div class="role-warning">
                        <i class="fas fa-eye"></i>
                        Read-only access. Your role: <?= ucfirst(str_replace('_', ' ', $employee_role)) ?>
                    </div>
                <?php elseif (!$can_edit_vitals): ?>
                    <div class="role-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Only nurses, doctors, and admins can edit vitals. Your role: <?= ucfirst(str_replace('_', ' ', $employee_role)) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="vitals-grid">
                        <div class="form-group">
                            <label for="blood_pressure">Blood Pressure</label>
                            <input type="text" id="blood_pressure" name="blood_pressure" 
                                   placeholder="120/80 mmHg"
                                   value="<?= htmlspecialchars($vitals_data['blood_pressure'] ?? '') ?>"
                                   <?= !$can_edit_vitals ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="heart_rate">Heart Rate (bpm)</label>
                            <input type="number" id="heart_rate" name="heart_rate" 
                                   placeholder="72"
                                   value="<?= htmlspecialchars($vitals_data['heart_rate'] ?? '') ?>"
                                   <?= !$can_edit_vitals ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="temperature">Temperature (°C)</label>
                            <input type="number" id="temperature" name="temperature" step="0.1"
                                   placeholder="36.5"
                                   value="<?= htmlspecialchars($vitals_data['temperature'] ?? '') ?>"
                                   <?= !$can_edit_vitals ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="respiratory_rate">Respiratory Rate (/min)</label>
                            <input type="number" id="respiratory_rate" name="respiratory_rate"
                                   placeholder="18"
                                   value="<?= htmlspecialchars($vitals_data['respiratory_rate'] ?? '') ?>"
                                   <?= !$can_edit_vitals ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="height">Height (cm)</label>
                            <input type="number" id="height" name="height" step="0.1"
                                   placeholder="170.0"
                                   value="<?= htmlspecialchars($vitals_data['height'] ?? '') ?>"
                                   <?= !$can_edit_vitals ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="weight">Weight (kg)</label>
                            <input type="number" id="weight" name="weight" step="0.1"
                                   placeholder="70.0"
                                   value="<?= htmlspecialchars($vitals_data['weight'] ?? '') ?>"
                                   <?= !$can_edit_vitals ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Section 3: Consultation Details -->
            <div class="section-card <?= !$can_edit_consultation ? 'disabled-section' : '' ?>">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-stethoscope"></i> Consultation Details
                    </h3>
                    <div class="action-buttons">
                        <?php if ($can_edit_consultation): ?>
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="save_consultation" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Consultation
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($can_order_lab): ?>
                            <a href="consultation_actions/order_lab_test.php?visit_id=<?= $visit_id ?>" class="btn btn-primary">
                                <i class="fas fa-flask"></i> Order Lab Test
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($can_prescribe): ?>
                            <a href="consultation_actions/issue_prescription.php?visit_id=<?= $visit_id ?>" class="btn btn-primary">
                                <i class="fas fa-pills"></i> Issue Prescription
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($can_order_followup): ?>
                            <a href="consultation_actions/order_followup.php?visit_id=<?= $visit_id ?>" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i> Order Follow-Up
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_read_only): ?>
                    <div class="role-warning">
                        <i class="fas fa-eye"></i>
                        Read-only access. Your role: <?= ucfirst(str_replace('_', ' ', $employee_role)) ?>
                        <?php if ($employee_role === 'bhw'): ?>
                            - Limited to patients in your assigned barangay
                        <?php elseif ($employee_role === 'dho'): ?>
                            - Limited to patients in your assigned district
                        <?php endif; ?>
                    </div>
                <?php elseif (!$can_edit_consultation): ?>
                    <div class="role-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Only doctors and admins can edit consultation details. Your role: <?= ucfirst(str_replace('_', ' ', $employee_role)) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="chief_complaint">Chief Complaint <span class="required">*</span></label>
                            <textarea id="chief_complaint" name="chief_complaint"
                                      placeholder="What brings the patient in today?"
                                      <?= !$can_edit_consultation ? 'disabled' : '' ?>><?= htmlspecialchars($consultation_data['chief_complaint'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label for="diagnosis">Diagnosis <span class="required">*</span></label>
                            <textarea id="diagnosis" name="diagnosis"
                                      placeholder="Primary and secondary diagnoses"
                                      <?= !$can_edit_consultation ? 'disabled' : '' ?>><?= htmlspecialchars($consultation_data['diagnosis'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label for="treatment_plan">Treatment Plan</label>
                            <textarea id="treatment_plan" name="treatment_plan"
                                      placeholder="Medications, procedures, lifestyle modifications"
                                      <?= !$can_edit_consultation ? 'disabled' : '' ?>><?= htmlspecialchars($consultation_data['treatment_plan'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks"
                                      placeholder="Additional notes and observations"
                                      <?= !$can_edit_consultation ? 'disabled' : '' ?>><?= htmlspecialchars($consultation_data['remarks'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="consultation_status">Consultation Status</label>
                            <select id="consultation_status" name="consultation_status" <?= !$can_edit_consultation ? 'disabled' : '' ?>>
                                <option value="pending" <?= ($consultation_data['consultation_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="completed" <?= ($consultation_data['consultation_status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="awaiting_followup" <?= ($consultation_data['consultation_status'] ?? '') === 'awaiting_followup' ? 'selected' : '' ?>>Pending Follow-Up</option>
                                <option value="referred" <?= ($consultation_data['consultation_status'] ?? '') === 'referred' ? 'selected' : '' ?>>Referred</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Section 4: Audit Info -->
            <?php if ($consultation_data): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-history"></i> Audit Information
                        </h3>
                    </div>

                    <div class="audit-info">
                        <div class="audit-item">
                            <span><strong>Created by:</strong> <?= htmlspecialchars($consultation_data['created_by_name'] . ' ' . $consultation_data['created_by_surname']) ?></span>
                            <span><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($consultation_data['created_at'])) ?></span>
                        </div>
                        <div class="audit-item">
                            <span><strong>Last updated by:</strong> <?= htmlspecialchars($consultation_data['updated_by_name'] . ' ' . $consultation_data['updated_by_surname']) ?></span>
                            <span><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($consultation_data['updated_at'])) ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <script>
        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                
                // Initial resize
                textarea.style.height = 'auto';
                textarea.style.height = (textarea.scrollHeight) + 'px';
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