<?php
/**
 * New Consultation Interface
 * Redesigned with create_referrals.php HTML structure for UI consistency
 * Allows doctors/admin/nurses to search for checked-in patients and create consultations
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

// Check if user is logged in and has proper role
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Allow admin, doctor, nurse, and pharmacist roles
$authorized_roles = ['admin', 'doctor', 'nurse', 'pharmacist'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    header('Location: ../management/' . strtolower($_SESSION['role']) . '/dashboard.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'User';
$employee_role = $_SESSION['role'];

// Include reusable topbar component
require_once $root_path . '/includes/topbar.php';

// Handle AJAX requests first
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'search_checked_in_patients') {
        try {
            $search = $_GET['search'] ?? '';
            $searchParam = "%{$search}%";
            
            // Search for patients with checked-in appointments
            $sql = "SELECT DISTINCT
                        v.visit_id,
                        v.patient_id,
                        v.appointment_id,
                        v.visit_date,
                        v.visit_status,
                        p.first_name,
                        p.last_name,
                        p.middle_name,
                        p.username as patient_code,
                        p.date_of_birth,
                        p.sex,
                        p.contact_number,
                        p.address,
                        a.scheduled_date,
                        a.scheduled_time,
                        COALESCE(s.service_name, 'General Consultation') as service_name,
                        a.status as appointment_status,
                        -- Check if consultation already exists
                        c.consultation_id,
                        c.consultation_status,
                        c.consultation_date,
                        -- Latest vitals from this visit
                        vt.vital_id,
                        vt.systolic_bp,
                        vt.diastolic_bp,
                        vt.heart_rate,
                        vt.respiratory_rate,
                        vt.temperature,
                        vt.weight,
                        vt.height,
                        vt.bmi,
                        vt.recorded_by,
                        vt.created_at as vitals_date
                    FROM visits v
                    INNER JOIN patients p ON v.patient_id = p.patient_id
                    INNER JOIN appointments a ON v.appointment_id = a.appointment_id
                    LEFT JOIN services s ON a.service_id = s.service_id
                    LEFT JOIN consultations c ON v.visit_id = c.visit_id
                    LEFT JOIN vitals vt ON v.visit_id = vt.visit_id
                    WHERE a.status IN ('checked_in', 'in_progress')
                    AND v.visit_status IN ('checked_in', 'active', 'in_progress')
                    AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.username LIKE ? 
                         OR p.contact_number LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?)
                    ORDER BY v.visit_date DESC, a.scheduled_time ASC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare SQL statement: ' . $conn->error);
            }
            
            $stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $patients = [];
            while ($row = $result->fetch_assoc()) {
                // Calculate age
                if ($row['date_of_birth']) {
                    $dob = new DateTime($row['date_of_birth']);
                    $now = new DateTime();
                    $row['age'] = $now->diff($dob)->y;
                }
                
                // Format full name
                $row['full_name'] = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
                
                // Determine consultation status
                if ($row['consultation_id']) {
                    $row['has_consultation'] = true;
                    $row['consultation_display_status'] = ucfirst($row['consultation_status']);
                } else {
                    $row['has_consultation'] = false;
                    $row['consultation_display_status'] = 'Not Started';
                }
                
                $patients[] = $row;
            }
            
            echo json_encode($patients);
            exit();
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Search failed: ' . $e->getMessage()
            ]);
            exit();
        }
    }
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_consultation') {
        try {
            $conn->begin_transaction();
            
            // Get form data
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            
            // Vitals data
            $systolic_bp = !empty($_POST['systolic_bp']) ? (int)$_POST['systolic_bp'] : null;
            $diastolic_bp = !empty($_POST['diastolic_bp']) ? (int)$_POST['diastolic_bp'] : null;
            $heart_rate = !empty($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : null;
            $respiratory_rate = !empty($_POST['respiratory_rate']) ? (int)$_POST['respiratory_rate'] : null;
            $temperature = !empty($_POST['temperature']) ? (float)$_POST['temperature'] : null;
            $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
            $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
            $vitals_remarks = trim($_POST['vitals_remarks'] ?? '');
            
            // Calculate BMI if both weight and height are provided
            $bmi = null;
            if ($weight && $height) {
                $height_m = $height / 100; // Convert cm to meters
                $bmi = round($weight / ($height_m * $height_m), 2);
            }
            
            // Consultation data
            $chief_complaint = trim($_POST['chief_complaint'] ?? '');
            $history_present_illness = trim($_POST['history_present_illness'] ?? '');
            $physical_examination = trim($_POST['physical_examination'] ?? '');
            $assessment_diagnosis = trim($_POST['assessment_diagnosis'] ?? '');
            $treatment_plan = trim($_POST['treatment_plan'] ?? '');
            $consultation_notes = trim($_POST['consultation_notes'] ?? '');
            $consultation_status = $_POST['consultation_status'] ?? 'completed';
            
            // Validation
            if (!$visit_id) {
                throw new Exception('Please select a patient from the list above.');
            }
            
            // For nurses in triage, only allow vitals input
            if (strtolower($employee_role) === 'nurse' && empty($chief_complaint) && empty($physical_examination)) {
                // Nurse is just entering vitals - this is allowed
                if (!$systolic_bp && !$diastolic_bp && !$heart_rate && !$temperature && !$weight && !$height) {
                    throw new Exception('Please enter at least one vital sign measurement.');
                }
            } else {
                // Full consultation requires at least chief complaint
                if (empty($chief_complaint)) {
                    throw new Exception('Chief complaint is required for consultation notes.');
                }
            }
            
            // Insert/update vitals if provided
            $vitals_id = null;
            if ($systolic_bp || $diastolic_bp || $heart_rate || $respiratory_rate || $temperature || $weight || $height) {
                // Check if vitals already exist for this visit
                $stmt = $conn->prepare("SELECT vital_id FROM vitals WHERE visit_id = ? ORDER BY created_at DESC LIMIT 1");
                if (!$stmt) {
                    throw new Exception('Failed to prepare vitals check query: ' . $conn->error);
                }
                $stmt->bind_param('i', $visit_id);
                $stmt->execute();
                $existing_vitals = $stmt->get_result()->fetch_assoc();
                
                if ($existing_vitals) {
                    // Update existing vitals
                    $stmt = $conn->prepare("
                        UPDATE vitals SET
                            systolic_bp = ?, diastolic_bp = ?, heart_rate = ?, 
                            respiratory_rate = ?, temperature = ?, weight = ?, height = ?, bmi = ?, 
                            recorded_by = ?, remarks = ?, updated_at = NOW()
                        WHERE vital_id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Failed to prepare vitals update query: ' . $conn->error);
                    }
                    $stmt->bind_param(
                        'iiiiddddiisi', 
                        $systolic_bp, $diastolic_bp, $heart_rate, 
                        $respiratory_rate, $temperature, $weight, $height, $bmi, 
                        $employee_id, $vitals_remarks, $existing_vitals['vital_id']
                    );
                    $vitals_id = $existing_vitals['vital_id'];
                } else {
                    // Insert new vitals
                    $stmt = $conn->prepare("
                        INSERT INTO vitals (
                            visit_id, systolic_bp, diastolic_bp, heart_rate, 
                            respiratory_rate, temperature, weight, height, bmi, 
                            recorded_by, remarks
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$stmt) {
                        throw new Exception('Failed to prepare vitals insert query: ' . $conn->error);
                    }
                    $stmt->bind_param(
                        'iiiiddddiis', 
                        $visit_id, $systolic_bp, $diastolic_bp, $heart_rate, 
                        $respiratory_rate, $temperature, $weight, $height, $bmi, 
                        $employee_id, $vitals_remarks
                    );
                }
                $stmt->execute();
                if (!$vitals_id) {
                    $vitals_id = $conn->insert_id;
                }
            }
            
            // Insert/update consultation notes (only if not just vitals-only for nurse)
            if (!empty($chief_complaint) || strtolower($employee_role) !== 'nurse') {
                // Check if consultation already exists for this visit
                $stmt = $conn->prepare("SELECT consultation_id FROM consultations WHERE visit_id = ?");
                if (!$stmt) {
                    throw new Exception('Failed to prepare consultation check query: ' . $conn->error);
                }
                $stmt->bind_param('i', $visit_id);
                $stmt->execute();
                $existing_consultation = $stmt->get_result()->fetch_assoc();
                
                if ($existing_consultation) {
                    // Update existing consultation
                    $stmt = $conn->prepare("
                        UPDATE consultations SET
                            chief_complaint = ?, history_present_illness = ?, physical_examination = ?,
                            assessment_diagnosis = ?, treatment_plan = ?, consultation_notes = ?,
                            consultation_status = ?, consulted_by = ?, updated_at = NOW()
                        WHERE consultation_id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Failed to prepare consultation update query: ' . $conn->error);
                    }
                    $stmt->bind_param(
                        'sssssssii',
                        $chief_complaint, $history_present_illness, $physical_examination,
                        $assessment_diagnosis, $treatment_plan, $consultation_notes,
                        $consultation_status, $employee_id, $existing_consultation['consultation_id']
                    );
                } else {
                    // Insert new consultation
                    $stmt = $conn->prepare("
                        INSERT INTO consultations (
                            visit_id, chief_complaint, history_present_illness, physical_examination,
                            assessment_diagnosis, treatment_plan, consultation_notes,
                            consultation_status, consulted_by, consultation_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if (!$stmt) {
                        throw new Exception('Failed to prepare consultation insert query: ' . $conn->error);
                    }
                    $stmt->bind_param(
                        'issssssssi',
                        $visit_id, $chief_complaint, $history_present_illness, $physical_examination,
                        $assessment_diagnosis, $treatment_plan, $consultation_notes,
                        $consultation_status, $employee_id
                    );
                }
                $stmt->execute();
            }
            
            $conn->commit();
            
            // Set appropriate success message based on role
            if (strtolower($employee_role) === 'nurse' && empty($chief_complaint)) {
                $success_message = "Patient vitals recorded successfully!";
            } else {
                $success_message = "Consultation saved successfully!";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Get checked-in patients for initial load (recent patients)
$recent_patients = [];
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            v.visit_id, v.patient_id, v.appointment_id, v.visit_date, v.visit_status,
            p.first_name, p.last_name, p.middle_name, p.username as patient_code,
            p.date_of_birth, p.sex, p.contact_number, p.address,
            a.scheduled_date, a.scheduled_time,
            COALESCE(s.service_name, 'General Consultation') as service_name,
            c.consultation_id, c.consultation_status,
            vt.vital_id, vt.systolic_bp, vt.diastolic_bp
        FROM visits v
        INNER JOIN patients p ON v.patient_id = p.patient_id
        INNER JOIN appointments a ON v.appointment_id = a.appointment_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN consultations c ON v.visit_id = c.visit_id
        LEFT JOIN vitals vt ON v.visit_id = vt.visit_id
        WHERE a.status IN ('checked_in', 'in_progress')
        AND v.visit_status IN ('checked_in', 'active', 'in_progress')
        ORDER BY v.visit_date DESC, a.scheduled_time ASC
        LIMIT 5
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare initial patient query: ' . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Calculate age
        if ($row['date_of_birth']) {
            $dob = new DateTime($row['date_of_birth']);
            $now = new DateTime();
            $row['age'] = $now->diff($dob)->y;
        }
        $row['full_name'] = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
        $recent_patients[] = $row;
    }
} catch (Exception $e) {
    // Ignore errors for initial load
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>New Consultation | CHO Koronadal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" /></head>
    <style>
        .search-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
        }

        .search-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .patient-table {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
        }

        .patient-table table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            min-width: 600px;
        }

        .patient-table th,
        .patient-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .patient-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #28a745;
        }

        .patient-table tbody tr:hover {
            background: #f8f9fa;
        }

        .patient-checkbox {
            width: 18px;
            height: 18px;
            margin-right: 0.5rem;
        }

        .consultation-form {
            opacity: 0.5;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .consultation-form.enabled {
            opacity: 1;
            pointer-events: auto;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .consultation-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #28a745;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .selected-patient {
            background: #d4edda !important;
            border-left: 4px solid #28a745;
        }

        .empty-search {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .patient-status {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .status-not-started {
            background: #f8d7da;
            color: #721c24;
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

        .loading {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .loading i {
            font-size: 2em;
            margin-bottom: 1rem;
        }

        .patient-card {
            display: none;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .patient-card:hover {
            border-color: #28a745;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);
        }

        .patient-card.selected {
            border-color: #28a745;
            background: #f8fff8;
        }

        .patient-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .patient-card-name {
            font-weight: 600;
            color: #28a745;
            font-size: 1.1em;
        }

        .patient-card-id {
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85em;
            color: #6c757d;
        }

        .patient-card-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            font-size: 0.9em;
        }

        .patient-card-detail {
            display: flex;
            flex-direction: column;
        }

        .patient-card-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.8em;
            margin-bottom: 0.1rem;
        }

        .patient-card-value {
            color: #333;
        }

        .patient-card-checkbox {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 20px;
            height: 20px;
        }

        .role-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin-bottom: 1.5rem;
        }

        .consultation-actions-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #17a2b8;
            opacity: 0.5;
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .consultation-actions-section.enabled {
            opacity: 1;
            pointer-events: auto;
        }

        .consultation-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-action {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            font-family: inherit;
            min-height: 80px;
            justify-content: center;
        }

        .btn-action i {
            font-size: 1.2em;
        }

        .btn-lab {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        .btn-lab:hover:not(:disabled) {
            background: linear-gradient(135deg, #138496, #117a8b);
            transform: translateY(-2px);
        }

        .btn-prescription {
            background: linear-gradient(135deg, #fd7e14, #e8681c);
            color: white;
        }

        .btn-prescription:hover:not(:disabled) {
            background: linear-gradient(135deg, #e8681c, #d4561e);
            transform: translateY(-2px);
        }

        .btn-followup {
            background: linear-gradient(135deg, #6f42c1, #5a359a);
            color: white;
        }

        .btn-followup:hover:not(:disabled) {
            background: linear-gradient(135deg, #5a359a, #4e2f87);
            transform: translateY(-2px);
        }

        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0.5rem 0;
        }

        @media (max-width: 768px) {
            .search-grid {
                grid-template-columns: 1fr;
            }

            .vitals-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }

            .patient-table table {
                display: none;
            }

            .patient-card {
                display: block;
            }
            
            .patient-card-details {
                grid-template-columns: 1fr;
            }

            .consultation-actions-grid {
                grid-template-columns: 1fr;
            }

            .btn-action {
                min-height: 60px;
                padding: 0.75rem;
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
        'title' => 'New Consultation',
        'back_url' => 'index.php',
        'user_type' => 'employee',
        'vendor_path' => '../../vendor/'
    ]);
    ?>

    <section class="homepage">
        <?php 
        // Render back button with modal
        renderBackButton([
            'back_url' => 'index.php',
            'button_text' => '← Back to Clinical Encounters',
            'modal_title' => 'Cancel Consultation?',
            'modal_message' => 'Are you sure you want to go back? Unsaved changes will be lost.',
            'confirm_text' => 'Yes, Go Back',
            'stay_text' => 'Stay'
        ]);
        ?>

        <div class="profile-wrapper" style="margin: 0 200px;">
            <!-- Role Information -->
            <div class="role-info">
                <strong>Welcome, <?= htmlspecialchars($employee_name) ?> (<?= ucfirst($employee_role) ?>)</strong>
                <ul style="margin: 0.5rem 0 0 0; padding-left: 1.5rem;">
                    <?php if (strtolower($employee_role) === 'nurse'): ?>
                        <li>As a nurse, you can record patient vital signs for triage purposes.</li>
                        <li>Consultation notes can be added by doctors after vital signs are recorded.</li>
                    <?php elseif (strtolower($employee_role) === 'pharmacist'): ?>
                        <li>As a pharmacist, you can record patient vital signs and complete consultation notes.</li>
                        <li>You can create prescriptions and manage medication orders for patients.</li>
                        <li>Search for checked-in patients to begin their consultation.</li>
                    <?php else: ?>
                        <li>You can record patient vital signs and complete consultation notes.</li>
                        <li>Search for checked-in patients to begin their consultation.</li>
                    <?php endif; ?>
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

            <!-- Patient Search Section -->
            <div class="form-section">
                <div class="search-container">
                    <h3><i class="fas fa-search"></i> Search Checked-In Patients</h3>
                    <div class="search-grid">
                        <div class="form-group">
                            <input type="text" 
                                   id="patientSearch" 
                                   class="form-control" 
                                   placeholder="Search by patient name, ID, or contact number..."
                                   autocomplete="off">
                        </div>
                        <button type="button" class="btn btn-primary" onclick="searchPatients()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>

            <!-- Patient Results Section -->
            <div class="form-section">
                <div class="patient-table">
                    <h3><i class="fas fa-users"></i> Available Patients</h3>
                    
                    <div id="loading" class="loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Searching patients...</p>
                    </div>
                    
                    <div id="emptyState" class="empty-search">
                        <i class="fas fa-user-clock"></i>
                        <h4>Recent Checked-In Patients</h4>
                        <p>Use the search box above to find specific patients, or select from recent checked-in patients below.</p>
                    </div>
                    
                    <div id="resultsContainer">
                        <!-- Desktop Table View -->
                        <div class="patient-table-container">
                            <table id="patientsTable">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Patient</th>
                                        <th>Age/Sex</th>
                                        <th>Contact</th>
                                        <th>Service</th>
                                        <th>Vitals</th>
                                        <th>Consultation</th>
                                    </tr>
                                </thead>
                                <tbody id="patientsTableBody">
                                    <?php foreach ($recent_patients as $patient): ?>
                                        <tr class="patient-row" data-visit-id="<?= $patient['visit_id'] ?>">
                                            <td>
                                                <input type="radio" 
                                                       name="selected_patient" 
                                                       value="<?= $patient['visit_id'] ?>" 
                                                       class="patient-checkbox"
                                                       data-patient-name="<?= htmlspecialchars($patient['full_name']) ?>"
                                                       data-patient-id="<?= $patient['patient_code'] ?>"
                                                       data-age="<?= $patient['age'] ?? '-' ?>"
                                                       data-sex="<?= $patient['sex'] ?>"
                                                       data-contact="<?= $patient['contact_number'] ?>">
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($patient['full_name']) ?></strong><br>
                                                <small><?= htmlspecialchars($patient['patient_code']) ?></small>
                                            </td>
                                            <td><?= $patient['age'] ?? '-' ?>/<?= $patient['sex'] ?></td>
                                            <td><?= $patient['contact_number'] ?? '-' ?></td>
                                            <td><?= htmlspecialchars($patient['service_name']) ?></td>
                                            <td>
                                                <?php if ($patient['vital_id']): ?>
                                                    <span class="patient-status status-completed">
                                                        <i class="fas fa-check"></i> Recorded
                                                    </span>
                                                <?php else: ?>
                                                    <span class="patient-status status-not-started">
                                                        <i class="fas fa-times"></i> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($patient['consultation_id']): ?>
                                                    <span class="patient-status status-<?= strtolower(str_replace('_', '-', $patient['consultation_status'])) ?>">
                                                        <?= ucfirst($patient['consultation_status']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="patient-status status-not-started">Not Started</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card View -->
                        <div id="patientCards">
                            <?php foreach ($recent_patients as $patient): ?>
                                <div class="patient-card" data-visit-id="<?= $patient['visit_id'] ?>" onclick="selectPatientCard(this)">
                                    <input type="radio" 
                                           name="selected_patient_mobile" 
                                           value="<?= $patient['visit_id'] ?>" 
                                           class="patient-card-checkbox"
                                           data-patient-name="<?= htmlspecialchars($patient['full_name']) ?>"
                                           data-patient-id="<?= $patient['patient_code'] ?>"
                                           data-age="<?= $patient['age'] ?? '-' ?>"
                                           data-sex="<?= $patient['sex'] ?>"
                                           data-contact="<?= $patient['contact_number'] ?>">
                                    <div class="patient-card-header">
                                        <div class="patient-card-name"><?= htmlspecialchars($patient['full_name']) ?></div>
                                        <div class="patient-card-id"><?= htmlspecialchars($patient['patient_code']) ?></div>
                                    </div>
                                    <div class="patient-card-details">
                                        <div class="patient-card-detail">
                                            <div class="patient-card-label">Age/Sex</div>
                                            <div class="patient-card-value"><?= $patient['age'] ?? '-' ?>/<?= $patient['sex'] ?></div>
                                        </div>
                                        <div class="patient-card-detail">
                                            <div class="patient-card-label">Contact</div>
                                            <div class="patient-card-value"><?= $patient['contact_number'] ?? '-' ?></div>
                                        </div>
                                        <div class="patient-card-detail">
                                            <div class="patient-card-label">Service</div>
                                            <div class="patient-card-value"><?= htmlspecialchars($patient['service_name']) ?></div>
                                        </div>
                                        <div class="patient-card-detail">
                                            <div class="patient-card-label">Status</div>
                                            <div class="patient-card-value">
                                                <?php if ($patient['consultation_id']): ?>
                                                    <?= ucfirst($patient['consultation_status']) ?>
                                                <?php else: ?>
                                                    Not Started
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consultation Form -->
            <div class="form-section">
                <form class="profile-card consultation-form" id="consultationForm" method="post">
                    <input type="hidden" name="action" value="save_consultation">
                    <input type="hidden" name="visit_id" id="selectedVisitId">
                    
                    <h3><i class="fas fa-stethoscope"></i> Patient Consultation</h3>
                    
                    <div id="selectedPatientInfo" class="selected-patient-info" style="display:none;">
                        <div style="background: #f8fff8; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #28a745;">
                            <strong>Selected Patient:</strong> <span id="selectedPatientName"></span><br>
                            <small>ID: <span id="selectedPatientId"></span> | Age/Sex: <span id="selectedPatientAge"></span></small>
                        </div>
                    </div>

                    <!-- Patient Vitals Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-heartbeat"></i> Vital Signs</h4>
                        <div class="vitals-grid">
                            <div class="form-group">
                                <label>Systolic BP (mmHg)</label>
                                <input type="number" name="systolic_bp" id="systolicBp" class="form-control" placeholder="120" min="60" max="300">
                            </div>
                            <div class="form-group">
                                <label>Diastolic BP (mmHg)</label>
                                <input type="number" name="diastolic_bp" id="diastolicBp" class="form-control" placeholder="80" min="40" max="200">
                            </div>
                            <div class="form-group">
                                <label>Heart Rate (bpm)</label>
                                <input type="number" name="heart_rate" id="heartRate" class="form-control" placeholder="72" min="40" max="200">
                            </div>
                            <div class="form-group">
                                <label>Respiratory Rate (/min)</label>
                                <input type="number" name="respiratory_rate" id="respiratoryRate" class="form-control" placeholder="16" min="8" max="60">
                            </div>
                            <div class="form-group">
                                <label>Temperature (°C)</label>
                                <input type="number" name="temperature" id="temperature" class="form-control" step="0.1" placeholder="36.5" min="35" max="42">
                            </div>
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" name="weight" id="weight" class="form-control" step="0.1" placeholder="70.0" min="1" max="300">
                            </div>
                            <div class="form-group">
                                <label>Height (cm)</label>
                                <input type="number" name="height" id="height" class="form-control" step="0.1" placeholder="170" min="50" max="250">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Vitals Remarks</label>
                            <textarea name="vitals_remarks" id="vitalsRemarks" class="form-control" placeholder="Additional notes about vital signs..." rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Consultation Notes Section -->
                    <?php if (strtolower($employee_role) !== 'nurse'): ?>
                    <div class="form-section">
                        <h4><i class="fas fa-notes-medical"></i> Consultation Notes</h4>
                        <div class="consultation-grid">
                            <div class="form-group">
                                <label>Chief Complaint *</label>
                                <textarea name="chief_complaint" id="chiefComplaint" class="form-control" placeholder="Patient's main concern or reason for visit..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label>History of Present Illness</label>
                                <textarea name="history_present_illness" id="historyPresentIllness" class="form-control" placeholder="Detailed history of the current illness..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Physical Examination</label>
                                <textarea name="physical_examination" id="physicalExamination" class="form-control" placeholder="Physical examination findings..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Assessment & Diagnosis</label>
                                <textarea name="assessment_diagnosis" id="assessmentDiagnosis" class="form-control" placeholder="Clinical assessment and diagnosis..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Treatment Plan</label>
                                <textarea name="treatment_plan" id="treatmentPlan" class="form-control" placeholder="Treatment plan and recommendations..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Additional Notes</label>
                                <textarea name="consultation_notes" id="consultationNotes" class="form-control" placeholder="Additional clinical notes or observations..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Consultation Status</label>
                                <select name="consultation_status" id="consultationStatus" class="form-control">
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed" selected>Completed</option>
                                    <option value="follow_up_required">Follow-up Required</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Consultation Actions Section -->
                    <?php if (strtolower($employee_role) !== 'nurse'): ?>
                    <div class="form-section consultation-actions-section" id="consultationActionsSection" style="display: none;">
                        <h4><i class="fas fa-clipboard-list"></i> Consultation Actions</h4>
                        <p class="text-muted">Additional actions available after selecting a patient:</p>
                        <div class="consultation-actions-grid">
                            <button type="button" class="btn btn-action btn-lab" id="orderLabTestBtn" onclick="openLabTestWindow()" disabled>
                                <i class="fas fa-flask"></i> Order Lab Tests
                            </button>
                            <button type="button" class="btn btn-action btn-prescription" id="issuePrescriptionBtn" onclick="openPrescriptionWindow()" disabled>
                                <i class="fas fa-prescription-bottle"></i> Issue Prescription
                            </button>
                            <button type="button" class="btn btn-action btn-followup" id="orderFollowupBtn" onclick="openFollowupWindow()" disabled>
                                <i class="fas fa-calendar-check"></i> Order Follow-up
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <?php if (strtolower($employee_role) === 'nurse'): ?>
                            <button type="submit" class="btn btn-primary" disabled>
                                <i class="fas fa-heartbeat"></i> Record Vital Signs
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary">
                                <i class="fas fa-save"></i> Save as Draft
                            </button>
                            <button type="submit" class="btn btn-primary" disabled>
                                <i class="fas fa-check-circle"></i> Complete Consultation
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        let selectedPatient = null;
        let searchTimeout = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Patient selection logic
            const patientCheckboxes = document.querySelectorAll('.patient-checkbox, .patient-card-checkbox');
            const consultationForm = document.getElementById('consultationForm');
            const submitBtn = consultationForm.querySelector('button[type="submit"]');
            const selectedVisitId = document.getElementById('selectedVisitId');
            const selectedPatientInfo = document.getElementById('selectedPatientInfo');
            const selectedPatientName = document.getElementById('selectedPatientName');
            const selectedPatientId = document.getElementById('selectedPatientId');
            const selectedPatientAge = document.getElementById('selectedPatientAge');

            patientCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        // Clear other selections
                        patientCheckboxes.forEach(cb => {
                            if (cb !== this) cb.checked = false;
                        });
                        
                        // Remove selection styling from all rows/cards
                        document.querySelectorAll('.patient-row').forEach(row => row.classList.remove('selected-patient'));
                        document.querySelectorAll('.patient-card').forEach(card => card.classList.remove('selected'));
                        
                        // Add selection styling
                        const container = this.closest('.patient-row') || this.closest('.patient-card');
                        if (container) {
                            container.classList.add(container.classList.contains('patient-row') ? 'selected-patient' : 'selected');
                        }
                        
                        // Update form
                        selectedVisitId.value = this.value;
                        selectedPatientName.textContent = this.dataset.patientName;
                        selectedPatientId.textContent = this.dataset.patientId;
                        selectedPatientAge.textContent = `${this.dataset.age}/${this.dataset.sex}`;
                        
                        // Show selected patient info and enable form
                        selectedPatientInfo.style.display = 'block';
                        consultationForm.classList.add('enabled');
                        submitBtn.disabled = false;
                        
                        // Load existing patient data if any
                        loadPatientData(this.value);
                    } else {
                        // Clear selection
                        selectedVisitId.value = '';
                        selectedPatientInfo.style.display = 'none';
                        consultationForm.classList.remove('enabled');
                        submitBtn.disabled = true;
                        
                        // Disable consultation actions
                        disableConsultationActions();
                        
                        // Clear form
                        clearConsultationForm();
                    }
                });
            });

            // Set up real-time search
            document.getElementById('patientSearch').addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchPatients(e.target.value);
                }, 300);
            });
        });

        // Mobile patient card selection function
        window.selectPatientCard = function(cardElement) {
            const checkbox = cardElement.querySelector('.patient-card-checkbox');
            if (checkbox && !checkbox.checked) {
                // Trigger the checkbox change event
                checkbox.checked = true;
                const event = new Event('change', { bubbles: true });
                checkbox.dispatchEvent(event);
            }
        };

        // Search for checked-in patients
        function searchPatients(searchTerm = '') {
            showLoading(true);
            
            fetch(`?action=search_checked_in_patients&search=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    showLoading(false);
                    
                    if (data.error) {
                        showError('Error searching patients: ' + data.message);
                        return;
                    }
                    
                    displaySearchResults(data);
                })
                .catch(error => {
                    showLoading(false);
                    showError('Network error: ' + error.message);
                });
        }

        // Display search results
        function displaySearchResults(patients) {
            const tableBody = document.getElementById('patientsTableBody');
            const patientCards = document.getElementById('patientCards');
            const emptyState = document.getElementById('emptyState');
            
            if (patients.length === 0) {
                emptyState.innerHTML = `
                    <i class="fas fa-user-clock"></i>
                    <h4>No Patients Found</h4>
                    <p>No checked-in patients found matching your search criteria.</p>
                `;
                emptyState.style.display = 'block';
                return;
            }
            
            emptyState.style.display = 'none';
            
            // Update table
            tableBody.innerHTML = patients.map(patient => `
                <tr class="patient-row" data-visit-id="${patient.visit_id}">
                    <td>
                        <input type="radio" 
                               name="selected_patient" 
                               value="${patient.visit_id}" 
                               class="patient-checkbox"
                               data-patient-name="${patient.full_name}"
                               data-patient-id="${patient.patient_code}"
                               data-age="${patient.age || '-'}"
                               data-sex="${patient.sex}"
                               data-contact="${patient.contact_number || ''}">
                    </td>
                    <td>
                        <strong>${patient.full_name}</strong><br>
                        <small>${patient.patient_code}</small>
                    </td>
                    <td>${patient.age || '-'}/${patient.sex}</td>
                    <td>${patient.contact_number || '-'}</td>
                    <td>${patient.service_name}</td>
                    <td>
                        ${patient.vital_id ? 
                          '<span class="patient-status status-completed"><i class="fas fa-check"></i> Recorded</span>' : 
                          '<span class="patient-status status-not-started"><i class="fas fa-times"></i> Pending</span>'}
                    </td>
                    <td>
                        ${patient.consultation_id ? 
                          `<span class="patient-status status-${patient.consultation_status?.replace('_', '-')}">${patient.consultation_display_status}</span>` : 
                          '<span class="patient-status status-not-started">Not Started</span>'}
                    </td>
                </tr>
            `).join('');
            
            // Update cards
            patientCards.innerHTML = patients.map(patient => `
                <div class="patient-card" data-visit-id="${patient.visit_id}" onclick="selectPatientCard(this)">
                    <input type="radio" 
                           name="selected_patient_mobile" 
                           value="${patient.visit_id}" 
                           class="patient-card-checkbox"
                           data-patient-name="${patient.full_name}"
                           data-patient-id="${patient.patient_code}"
                           data-age="${patient.age || '-'}"
                           data-sex="${patient.sex}"
                           data-contact="${patient.contact_number || ''}">
                    <div class="patient-card-header">
                        <div class="patient-card-name">${patient.full_name}</div>
                        <div class="patient-card-id">${patient.patient_code}</div>
                    </div>
                    <div class="patient-card-details">
                        <div class="patient-card-detail">
                            <div class="patient-card-label">Age/Sex</div>
                            <div class="patient-card-value">${patient.age || '-'}/${patient.sex}</div>
                        </div>
                        <div class="patient-card-detail">
                            <div class="patient-card-label">Contact</div>
                            <div class="patient-card-value">${patient.contact_number || '-'}</div>
                        </div>
                        <div class="patient-card-detail">
                            <div class="patient-card-label">Service</div>
                            <div class="patient-card-value">${patient.service_name}</div>
                        </div>
                        <div class="patient-card-detail">
                            <div class="patient-card-label">Status</div>
                            <div class="patient-card-value">${patient.consultation_display_status || 'Not Started'}</div>
                        </div>
                    </div>
                </div>
            `).join('');
            
            // Reattach event listeners
            setupPatientSelection();
        }

        // Setup patient selection for dynamically added elements
        function setupPatientSelection() {
            const checkboxes = document.querySelectorAll('.patient-checkbox, .patient-card-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', handlePatientSelection);
            });
        }

        function handlePatientSelection(event) {
            const checkbox = event.target;
            if (checkbox.checked) {
                // Clear other selections
                document.querySelectorAll('.patient-checkbox, .patient-card-checkbox').forEach(cb => {
                    if (cb !== checkbox) cb.checked = false;
                });
                
                // Remove selection styling
                document.querySelectorAll('.patient-row').forEach(row => row.classList.remove('selected-patient'));
                document.querySelectorAll('.patient-card').forEach(card => card.classList.remove('selected'));
                
                // Add selection styling
                const container = checkbox.closest('.patient-row') || checkbox.closest('.patient-card');
                if (container) {
                    container.classList.add(container.classList.contains('patient-row') ? 'selected-patient' : 'selected');
                }
                
                // Update form
                document.getElementById('selectedVisitId').value = checkbox.value;
                document.getElementById('selectedPatientName').textContent = checkbox.dataset.patientName;
                document.getElementById('selectedPatientId').textContent = checkbox.dataset.patientId;
                document.getElementById('selectedPatientAge').textContent = `${checkbox.dataset.age}/${checkbox.dataset.sex}`;
                
                // Show form
                document.getElementById('selectedPatientInfo').style.display = 'block';
                document.getElementById('consultationForm').classList.add('enabled');
                document.querySelector('#consultationForm button[type="submit"]').disabled = false;
                
                // Enable consultation actions for non-nurse roles
                enableConsultationActions(checkbox.value);
                
                // Load patient data
                loadPatientData(checkbox.value);
            }
        }

        // Load existing patient data
        function loadPatientData(visitId) {
            // This would typically make an AJAX call to get existing data
            // For now, we'll just clear the form
            clearConsultationForm();
        }

        // Clear consultation form
        function clearConsultationForm() {
            const form = document.getElementById('consultationForm');
            const inputs = form.querySelectorAll('input[type="text"], input[type="number"], textarea, select');
            inputs.forEach(input => {
                if (input.name !== 'action' && input.name !== 'visit_id') {
                    input.value = '';
                }
            });
        }

        // Utility functions
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        function showError(message) {
            alert('Error: ' + message);
        }

        function showSuccess(message) {
            alert(message);
        }

        // Consultation Actions Functions
        let currentVisitId = null;

        function enableConsultationActions(visitId) {
            currentVisitId = visitId;
            const actionsSection = document.getElementById('consultationActionsSection');
            const actionButtons = document.querySelectorAll('#orderLabTestBtn, #issuePrescriptionBtn, #orderFollowupBtn');
            
            if (actionsSection) {
                actionsSection.style.display = 'block';
                actionsSection.classList.add('enabled');
            }
            
            actionButtons.forEach(button => {
                button.disabled = false;
            });
        }

        function disableConsultationActions() {
            currentVisitId = null;
            const actionsSection = document.getElementById('consultationActionsSection');
            const actionButtons = document.querySelectorAll('#orderLabTestBtn, #issuePrescriptionBtn, #orderFollowupBtn');
            
            if (actionsSection) {
                actionsSection.style.display = 'none';
                actionsSection.classList.remove('enabled');
            }
            
            actionButtons.forEach(button => {
                button.disabled = true;
            });
        }

        function openLabTestWindow() {
            if (!currentVisitId) {
                showError('Please select a patient first.');
                return;
            }
            
            const url = `consultation_actions/order_lab_test.php?visit_id=${currentVisitId}`;
            const windowFeatures = 'width=1000,height=700,scrollbars=yes,resizable=yes,menubar=no,toolbar=no,location=no,status=no';
            
            const popup = window.open(url, 'OrderLabTest', windowFeatures);
            
            if (!popup) {
                showError('Popup blocked. Please allow popups for this site.');
                return;
            }
            
            // Focus on the popup window
            popup.focus();
            
            // Optional: Listen for popup close to refresh data
            const checkClosed = setInterval(() => {
                if (popup.closed) {
                    clearInterval(checkClosed);
                    // Optionally refresh patient data or show success message
                    console.log('Lab test window closed');
                }
            }, 1000);
        }

        function openPrescriptionWindow() {
            if (!currentVisitId) {
                showError('Please select a patient first.');
                return;
            }
            
            const url = `consultation_actions/issue_prescription.php?visit_id=${currentVisitId}`;
            const windowFeatures = 'width=1000,height=700,scrollbars=yes,resizable=yes,menubar=no,toolbar=no,location=no,status=no';
            
            const popup = window.open(url, 'IssuePrescription', windowFeatures);
            
            if (!popup) {
                showError('Popup blocked. Please allow popups for this site.');
                return;
            }
            
            // Focus on the popup window
            popup.focus();
            
            // Optional: Listen for popup close to refresh data
            const checkClosed = setInterval(() => {
                if (popup.closed) {
                    clearInterval(checkClosed);
                    // Optionally refresh patient data or show success message
                    console.log('Prescription window closed');
                }
            }, 1000);
        }

        function openFollowupWindow() {
            if (!currentVisitId) {
                showError('Please select a patient first.');
                return;
            }
            
            const url = `consultation_actions/order_followup.php?visit_id=${currentVisitId}`;
            const windowFeatures = 'width=1000,height=700,scrollbars=yes,resizable=yes,menubar=no,toolbar=no,location=no,status=no';
            
            const popup = window.open(url, 'OrderFollowup', windowFeatures);
            
            if (!popup) {
                showError('Popup blocked. Please allow popups for this site.');
                return;
            }
            
            // Focus on the popup window
            popup.focus();
            
            // Optional: Listen for popup close to refresh data
            const checkClosed = setInterval(() => {
                if (popup.closed) {
                    clearInterval(checkClosed);
                    // Optionally refresh patient data or show success message
                    console.log('Follow-up window closed');
                }
            }, 1000);
        }
    </script>
</body>
</html>