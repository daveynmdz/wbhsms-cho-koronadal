<?php
// Use patient session configuration
require_once __DIR__ . '/../../../config/session/patient_session.php';
require_once __DIR__ . '/../../../config/db.php';

// Only allow logged-in patients
$patient_id = isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
if (!$patient_id) {
    header('Location: ../auth/patient_login.php');
    exit();
}

// Fetch patient info with barangay name
$stmt = $pdo->prepare("
    SELECT p.*, b.barangay_name 
    FROM patients p 
    LEFT JOIN barangay b ON p.barangay_id = b.barangay_id 
    WHERE p.patient_id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    die('Patient not found.');
}
// Fetch personal_information for this patient
$stmt = $pdo->prepare("SELECT * FROM personal_information WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$personal_information = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch emergency_contact for this patient
$stmt = $pdo->prepare("SELECT * FROM emergency_contact WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$emergency_contact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch lifestyle_information for this patient  
$stmt = $pdo->prepare("SELECT * FROM lifestyle_information WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$lifestyle_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Handle form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'] ?? '';

    if ($form_type === 'patients_info') {
        // Handle patients table updates (conditionally editable fields only)
        $patients_updates = [];
        $patients_params = [];

        // Conditionally editable fields based on checkbox states
        if (isset($_POST['is_pwd']) && $_POST['is_pwd'] === '1') {
            $patients_updates[] = "isPWD = ?";
            $patients_params[] = '1';

            $patients_updates[] = "pwd_id_number = ?";
            $patients_params[] = trim($_POST['pwd_id_number'] ?? '');
        } else {
            $patients_updates[] = "isPWD = ?";
            $patients_params[] = '0';
            $patients_updates[] = "pwd_id_number = ?";
            $patients_params[] = null;
        }

        if (isset($_POST['is_philhealth']) && $_POST['is_philhealth'] === '1') {
            $patients_updates[] = "isPhilHealth = ?";
            $patients_params[] = '1';

            $patients_updates[] = "philhealth_type = ?";
            $patients_params[] = trim($_POST['philhealth_type'] ?? '');

            $patients_updates[] = "philhealth_id_number = ?";
            $patients_params[] = trim($_POST['philhealth_id_number'] ?? '');
        } else {
            $patients_updates[] = "isPhilHealth = ?";
            $patients_params[] = '0';
            $patients_updates[] = "philhealth_type = ?";
            $patients_params[] = null;
            $patients_updates[] = "philhealth_id_number = ?";
            $patients_params[] = null;
        }

        if (isset($_POST['is_senior']) && $_POST['is_senior'] === '1') {
            $patients_updates[] = "isSenior = ?";
            $patients_params[] = '1';

            $patients_updates[] = "senior_citizen_id = ?";
            $patients_params[] = trim($_POST['senior_citizen_id'] ?? '');
        } else {
            $patients_updates[] = "isSenior = ?";
            $patients_params[] = '0';
            $patients_updates[] = "senior_citizen_id = ?";
            $patients_params[] = null;
        }

        $patients_params[] = $patient_id;
        $sql = "UPDATE patients SET " . implode(', ', $patients_updates) . " WHERE patient_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($patients_params);
        $_SESSION['snackbar_message'] = 'Patient information updated.';
    } elseif ($form_type === 'personal_info') {
        // Handle personal_information table updates (blood_type, civil_status, religion, occupation, street)
        $fields = [
            'blood_type',
            'civil_status',
            'religion',
            'occupation'
        ];

        // Add street field if it's in the POST data (could be from Personal Info form or Home Address form)
        if (isset($_POST['street'])) {
            $fields[] = 'street';
        }

        $updates = [];
        $params = [];
        foreach ($fields as $field) {
            $updates[] = "$field = ?";
            $params[] = trim($_POST[$field] ?? '');
        }
        $params[] = $patient_id;

        $stmt = $pdo->prepare("SELECT id FROM personal_information WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        if ($stmt->fetch()) {
            $sql = "UPDATE personal_information SET " . implode(', ', $updates) . " WHERE patient_id = ?";
        } else {
            $fields_str = implode(', ', $fields) . ', patient_id';
            $qmarks = rtrim(str_repeat('?, ', count($fields)), ', ') . ', ?';
            $sql = "INSERT INTO personal_information ($fields_str) VALUES ($qmarks)";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Set appropriate success message
        if (isset($_POST['street']) && count($fields) === 1) {
            $_SESSION['snackbar_message'] = 'Address information saved.';
        } else {
            $_SESSION['snackbar_message'] = 'Personal information saved.';
        }
    } elseif ($form_type === 'emergency_contact') {
        // Handle emergency_contact table updates with correct field names
        $fields = ['emergency_first_name', 'emergency_middle_name', 'emergency_last_name', 'emergency_relationship', 'emergency_contact_number'];
        $form_fields = ['ec_first_name', 'ec_middle_name', 'ec_last_name', 'ec_relationship', 'ec_contact_number'];
        $updates = [];
        $params = [];
        foreach ($fields as $i => $col) {
            $updates[] = "$col = ?";
            $params[] = trim($_POST[$form_fields[$i]] ?? '');
        }
        $params[] = $patient_id;

        $stmt = $pdo->prepare("SELECT contact_id FROM emergency_contact WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        if ($stmt->fetch()) {
            $sql = "UPDATE emergency_contact SET " . implode(', ', $updates) . " WHERE patient_id = ?";
        } else {
            $fields_str = implode(', ', $fields) . ', patient_id';
            $qmarks = rtrim(str_repeat('?, ', count($fields)), ', ') . ', ?';
            $sql = "INSERT INTO emergency_contact ($fields_str) VALUES ($qmarks)";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $_SESSION['snackbar_message'] = 'Emergency contact saved.';
    } elseif ($form_type === 'lifestyle_info') {
        // Handle lifestyle_information table updates with correct field names
        $fields = ['smoking_status', 'alcohol_intake', 'physical_act', 'diet_habit'];
        $updates = [];
        $params = [];
        foreach ($fields as $field) {
            $updates[] = "$field = ?";
            $params[] = trim($_POST[$field] ?? '');
        }
        $params[] = $patient_id;

        $stmt = $pdo->prepare("SELECT id FROM lifestyle_information WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        if ($stmt->fetch()) {
            $sql = "UPDATE lifestyle_information SET " . implode(', ', $updates) . " WHERE patient_id = ?";
        } else {
            $fields_str = implode(', ', $fields) . ', patient_id';
            $qmarks = rtrim(str_repeat('?, ', count($fields)), ', ') . ', ?';
            $sql = "INSERT INTO lifestyle_information ($fields_str) VALUES ($qmarks)";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $_SESSION['snackbar_message'] = 'Lifestyle information saved.';
    }

    // Set a session flag for snackbar
    $_SESSION['show_snackbar'] = true;
    // Redirect to self to prevent resubmission and reload updated data (PRG pattern)
    header('Location: profile_edit.php');
    exit();
}

function h($v)
{
    return htmlspecialchars($v ?? '');
}
$profile_photo_url = !empty($patient['profile_photo']) ? 'images/' . $patient['profile_photo'] : 'https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Edit Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit-responsive.css" />
    <link rel="stylesheet" href="../../../assets/css/profile-edit.css" />
    <link rel="stylesheet" href="../../../assets/css/edit.css">
    <link rel="stylesheet" href="../../../vendor/cropper-modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
    <script src="../../../vendor/profile-photo-cropper.js"></script>
</head>

<body>
    <!-- Snackbar notification -->
    <div id="snackbar" style="display:none;position:fixed;left:50%;bottom:40px;transform:translateX(-50%);background:#323232;color:#fff;padding:1em 2em;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.18);font-size:1.1em;z-index:99999;opacity:0;transition:opacity 0.3s;">
        <span id="snackbar-text"></span>
    </div>

    <!-- Top Bar -->
    <header class="topbar">
        <div>
            <a href="../dashboard.php" class="topbar-logo" style="pointer-events: none; cursor: default;">
                <picture>
                    <source media="(max-width: 600px)"
                        srcset="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128">
                    <img src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527"
                        alt="City Health Logo" class="responsive-logo" />
                </picture>
            </a>
        </div>
        <div class="topbar-title" style="color: #ffffff;">Edit Patient Profile</div>
        <div class="topbar-userinfo">
            <div class="topbar-usertext">
                <strong style="color: #ffffff;">
                    <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                </strong><br>
                <small style="color: #ffffff;">Patient</small>
            </div>
            <img src="../../../vendor/photo_controller.php?patient_id=<?= urlencode($patient_id) ?>" alt="User Profile"
                class="topbar-userphoto"
                onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';" />
        </div>
    </header>

    <section class="homepage">
        <div class="edit-profile-toolbar-flex">
            <button type="button" class="btn btn-cancel floating-back-btn" id="backCancelBtn">&#8592; Back /
                Cancel</button>
            <!-- Custom Back/Cancel Confirmation Modal -->
            <div id="backCancelModal" class="custom-modal" style="display:none;">
                <div class="custom-modal-content">
                    <h3>Cancel Editing?</h3>
                    <p>Are you sure you want to go back/cancel? Unsaved changes will be lost.</p>
                    <div class="custom-modal-actions">
                        <button type="button" class="btn btn-danger" id="modalCancelBtn">Yes, Cancel</button>
                        <button type="button" class="btn btn-secondary" id="modalStayBtn">Stay</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="profile-wrapper">
            <!-- Reminders Box -->
            <div class="reminders-box">
                <strong>Reminders:</strong>
                <ul>
                    <li>Double-check your information before saving.</li>
                    <li>Fields marked with * are required.</li>
                    <li>Click 'Save' after editing each section.</li>
                    <li>To edit your Name, Date of Birth, Age, Sex, Contact Number, Email, and/or Barangay, please go to User Settings.</li>
                </ul>
            </div>

            <!-- Profile Photo Form -->
            <div class="form-section">
                <form class="profile-card" id="profilePhotoForm" method="post" enctype="multipart/form-data" action="profile_photo_upload.php">
                    <h3><i class="fas fa-camera"></i> Profile Photo</h3>
                    <div class="photo-upload-container">
                        <div class="photo-preview">
                            <img src="../../vendor/photo_controller.php?patient_id=<?= urlencode($patient_id) ?>" alt="Profile Photo"
                                id="profilePhotoPreview" class="profile-photo-img"
                                onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';" />
                        </div>
                        <div class="photo-upload-controls">
                            <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/*" class="file-input" />
                            <div class="photo-requirements">
                                <strong>Requirements:</strong>
                                <ul>
                                    <li>2x2-sized photo</li>
                                    <li>Under 10 MB only</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" id="savePhotoBtn" disabled>
                            <i class="fas fa-save"></i> Save Photo
                        </button>
                    </div>
                </form>
            </div>

            <!-- Patient Information Table (Read-only) -->
            <div class="form-section">
                <div class="profile-card">
                    <h3><i class="fas fa-user"></i> Patient Information</h3>
                    <div class="readonly-info-grid">
                        <div class="info-row">
                            <div class="info-item">
                                <label>Last Name</label>
                                <input type="text" value="<?= h($patient['last_name']) ?>" readonly class="readonly-field">
                            </div>
                            <div class="info-item">
                                <label>First Name</label>
                                <input type="text" value="<?= h($patient['first_name']) ?>" readonly class="readonly-field">
                            </div>
                            <div class="info-item">
                                <label>Middle Name</label>
                                <input type="text" value="<?= h($patient['middle_name']) ?>" readonly class="readonly-field">
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-item">
                                <label>Date of Birth</label>
                                <input type="date" value="<?= h($patient['date_of_birth']) ?>" readonly class="readonly-field">
                            </div>
                            <div class="info-item">
                                <label>Sex</label>
                                <input type="text" value="<?= h($patient['sex']) ?>" readonly class="readonly-field">
                            </div>
                            <div class="info-item">
                                <label>Contact Number</label>
                                <input type="text" value="<?= h($patient['contact_number']) ?>" readonly class="readonly-field">
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-item">
                                <label>Email</label>
                                <input type="email" value="<?= h($patient['email']) ?>" readonly class="readonly-field">
                            </div>
                            <div class="info-item">
                                <label>Barangay</label>
                                <input type="text" value="<?= h($patient['barangay_name'] ?? 'Not Set') ?>" readonly class="readonly-field">
                            </div>
                        </div>
                    </div>
                    <div class="readonly-notice">
                        <i class="fas fa-info-circle"></i>
                        To edit these fields, please contact the administrator or go to User Settings.
                    </div>
                </div>
            </div>

            <!-- Patient Status Information Form -->
            <div class="form-section">
                <form class="profile-card" id="patientsInfoForm" method="post">
                    <input type="hidden" name="form_type" value="patients_info">
                    <h3><i class="fas fa-id-card"></i> Patient Status Information</h3>

                    <!-- PWD Information -->
                    <div class="status-section">
                        <h4>Person with Disability (PWD)</h4>
                        <div class="checkbox-container">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_pwd" value="1" <?= ($patient['isPWD'] ?? '') == '1' ? 'checked' : '' ?> id="pwdCheckbox">
                                <span class="checkmark"></span>
                                I am a Person with Disability (PWD)
                            </label>
                        </div>
                        <div id="pwdIdField" class="conditional-field" style="<?= ($patient['isPWD'] ?? '') == '1' ? '' : 'display: none;' ?>">
                            <label>PWD ID Number</label>
                            <input type="text" name="pwd_id_number" value="<?= h($patient['pwd_id_number'] ?? '') ?>" maxlength="50" class="form-input">
                        </div>
                    </div>

                    <!-- PhilHealth Information -->
                    <div class="status-section">
                        <h4>PhilHealth Information</h4>
                        <div class="checkbox-container">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_philhealth" value="1" <?= ($patient['isPhilHealth'] ?? '') == '1' ? 'checked' : '' ?> id="philhealthCheckbox">
                                <span class="checkmark"></span>
                                I have PhilHealth
                            </label>
                        </div>
                        <div id="philhealthFields" class="conditional-fields" style="<?= ($patient['isPhilHealth'] ?? '') == '1' ? '' : 'display: none;' ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>PhilHealth Type *</label>
                                    <select name="philhealth_type" class="form-select">
                                        <option value="">Select Type</option>
                                        <option value="Indigent" <?= ($patient['philhealth_type'] ?? '') === 'Indigent' ? 'selected' : '' ?>>Indigent</option>
                                        <option value="Sponsored" <?= ($patient['philhealth_type'] ?? '') === 'Sponsored' ? 'selected' : '' ?>>Sponsored</option>
                                        <option value="Lifetime Member" <?= ($patient['philhealth_type'] ?? '') === 'Lifetime Member' ? 'selected' : '' ?>>Lifetime Member</option>
                                        <option value="Senior Citizen" <?= ($patient['philhealth_type'] ?? '') === 'Senior Citizen' ? 'selected' : '' ?>>Senior Citizen</option>
                                        <option value="PWD" <?= ($patient['philhealth_type'] ?? '') === 'PWD' ? 'selected' : '' ?>>PWD</option>
                                        <option value="Employed Private" <?= ($patient['philhealth_type'] ?? '') === 'Employed Private' ? 'selected' : '' ?>>Employed Private</option>
                                        <option value="Employed Government" <?= ($patient['philhealth_type'] ?? '') === 'Employed Government' ? 'selected' : '' ?>>Employed Government</option>
                                        <option value="Individual Paying" <?= ($patient['philhealth_type'] ?? '') === 'Individual Paying' ? 'selected' : '' ?>>Individual Paying</option>
                                        <option value="OFW" <?= ($patient['philhealth_type'] ?? '') === 'OFW' ? 'selected' : '' ?>>OFW</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>PhilHealth ID Number</label>
                                    <input type="text" name="philhealth_id_number" value="<?= h($patient['philhealth_id_number'] ?? '') ?>"
                                        pattern="\d{2}-\d{9}-\d{1}" maxlength="14" placeholder="XX-XXXXXXXXX-X" class="form-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Senior Citizen Information -->
                    <div class="status-section">
                        <h4>Senior Citizen Information</h4>
                        <div class="checkbox-container">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_senior" value="1" <?= ($patient['isSenior'] ?? '') == '1' ? 'checked' : '' ?> id="seniorCheckbox">
                                <span class="checkmark"></span>
                                I am a Senior Citizen (60 years and above)
                            </label>
                        </div>
                        <div id="seniorIdField" class="conditional-field" style="<?= ($patient['isSenior'] ?? '') == '1' ? '' : 'display: none;' ?>">
                            <label>Senior Citizen ID Number</label>
                            <input type="text" name="senior_citizen_id" value="<?= h($patient['senior_citizen_id'] ?? '') ?>" maxlength="50" class="form-input">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i> Save Patient Status
                        </button>
                    </div>
                </form>
            </div>

            <!-- Personal Information Form -->
            <div class="form-section">
                <form class="profile-card" id="personalInfoForm" method="post">
                    <input type="hidden" name="form_type" value="personal_info">
                    <h3><i class="fas fa-user-circle"></i> Personal Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Blood Type</label>
                            <select name="blood_type" class="form-select">
                                <option value="">Select Blood Type</option>
                                <option value="A+" <?= ($personal_information['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                <option value="A-" <?= ($personal_information['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                <option value="B+" <?= ($personal_information['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                <option value="B-" <?= ($personal_information['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                <option value="AB+" <?= ($personal_information['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                <option value="AB-" <?= ($personal_information['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                <option value="O+" <?= ($personal_information['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                <option value="O-" <?= ($personal_information['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Civil Status *</label>
                            <select name="civil_status" required class="form-select">
                                <option value="">Select Civil Status</option>
                                <option value="Single" <?= ($personal_information['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= ($personal_information['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                                <option value="Divorced" <?= ($personal_information['civil_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                                <option value="Widowed" <?= ($personal_information['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                <option value="Separated" <?= ($personal_information['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Religion *</label>
                            <select name="religion" required class="form-select">
                                <option value="">Select Religion</option>
                                <option value="Roman Catholic" <?= ($personal_information['religion'] ?? '') === 'Roman Catholic' ? 'selected' : '' ?>>Roman Catholic</option>
                                <option value="Protestant" <?= ($personal_information['religion'] ?? '') === 'Protestant' ? 'selected' : '' ?>>Protestant</option>
                                <option value="Islam" <?= ($personal_information['religion'] ?? '') === 'Islam' ? 'selected' : '' ?>>Islam</option>
                                <option value="Buddhism" <?= ($personal_information['religion'] ?? '') === 'Buddhism' ? 'selected' : '' ?>>Buddhism</option>
                                <option value="Hinduism" <?= ($personal_information['religion'] ?? '') === 'Hinduism' ? 'selected' : '' ?>>Hinduism</option>
                                <option value="Judaism" <?= ($personal_information['religion'] ?? '') === 'Judaism' ? 'selected' : '' ?>>Judaism</option>
                                <option value="Iglesia ni Cristo" <?= ($personal_information['religion'] ?? '') === 'Iglesia ni Cristo' ? 'selected' : '' ?>>Iglesia ni Cristo</option>
                                <option value="Seventh-day Adventist" <?= ($personal_information['religion'] ?? '') === 'Seventh-day Adventist' ? 'selected' : '' ?>>Seventh-day Adventist</option>
                                <option value="Jehovah's Witness" <?= ($personal_information['religion'] ?? '') === 'Jehovah\'s Witness' ? 'selected' : '' ?>>Jehovah's Witness</option>
                                <option value="Born Again Christian" <?= ($personal_information['religion'] ?? '') === 'Born Again Christian' ? 'selected' : '' ?>>Born Again Christian</option>
                                <option value="Other" <?= ($personal_information['religion'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                <option value="None" <?= ($personal_information['religion'] ?? '') === 'None' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" maxlength="50" value="<?= h($personal_information['occupation'] ?? '') ?>" class="form-input">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i> Save Personal Information
                        </button>
                    </div>
                </form>
            </div>

            <!-- Home Address Form -->
            <div class="form-section">
                <form class="profile-card" id="homeAddressForm" method="post">
                    <input type="hidden" name="form_type" value="personal_info">
                    <h3><i class="fas fa-home"></i> Home Address</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Street Address</label>
                            <input type="text" name="street" maxlength="100" value="<?= h($personal_information['street'] ?? '') ?>"
                                placeholder="House/Unit Number, Street Name" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>Barangay</label>
                            <input type="text" value="<?= h($patient['barangay_name'] ?? 'Not Set') ?>" readonly class="readonly-field">
                        </div>

                        <div class="form-group">
                            <label>City</label>
                            <input type="text" value="Koronadal" readonly class="readonly-field">
                        </div>
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" value="South Cotabato" readonly class="readonly-field">
                        </div>
                        <div class="form-group">
                            <label>ZIP Code</label>
                            <input type="text" value="9506" readonly class="readonly-field">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i> Save Address
                        </button>
                    </div>
                </form>
            </div>

            <!-- Emergency Contact Form -->
            <div class="form-section">
                <form class="profile-card" id="emergencyContactForm" method="post">
                    <input type="hidden" name="form_type" value="emergency_contact">
                    <h3><i class="fas fa-phone"></i> Emergency Contact</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="ec_first_name" value="<?= h($emergency_contact['emergency_first_name'] ?? '') ?>"
                                required class="form-input">
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="ec_middle_name" value="<?= h($emergency_contact['emergency_middle_name'] ?? '') ?>"
                                class="form-input">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="ec_last_name" value="<?= h($emergency_contact['emergency_last_name'] ?? '') ?>"
                                required class="form-input">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Relationship *</label>
                            <select name="ec_relationship" required class="form-select">
                                <option value="">Select Relationship</option>
                                <option value="Spouse" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                                <option value="Parent" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Parent' ? 'selected' : '' ?>>Parent</option>
                                <option value="Child" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Child' ? 'selected' : '' ?>>Child</option>
                                <option value="Sibling" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                                <option value="Relative" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Relative' ? 'selected' : '' ?>>Relative</option>
                                <option value="Friend" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Friend' ? 'selected' : '' ?>>Friend</option>
                                <option value="Guardian" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Guardian' ? 'selected' : '' ?>>Guardian</option>
                                <option value="Other" <?= ($emergency_contact['emergency_relationship'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Contact Number *</label>
                            <input type="text" name="ec_contact_number" value="<?= h($emergency_contact['emergency_contact_number'] ?? '') ?>"
                                required pattern="(\+63|0)[0-9]{10}" maxlength="13" placeholder="+63xxxxxxxxxx or 09xxxxxxxxx" class="form-input">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i> Save Emergency Contact
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lifestyle Information Form -->
            <div class="form-section">
                <form class="profile-card" id="lifestyleInfoForm" method="post">
                    <input type="hidden" name="form_type" value="lifestyle_info">
                    <h3><i class="fas fa-heart"></i> Lifestyle Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Smoking Status</label>
                            <select name="smoking_status" class="form-select">
                                <option value="">Select</option>
                                <option value="Never" <?= ($lifestyle_info['smoking_status'] ?? '') === 'Never' ? 'selected' : '' ?>>Never</option>
                                <option value="Former smoker" <?= ($lifestyle_info['smoking_status'] ?? '') === 'Former smoker' ? 'selected' : '' ?>>Former smoker</option>
                                <option value="Light smoker (1-10 cigarettes/day)" <?= ($lifestyle_info['smoking_status'] ?? '') === 'Light smoker (1-10 cigarettes/day)' ? 'selected' : '' ?>>Light smoker (1-10 cigarettes/day)</option>
                                <option value="Moderate smoker (11-20 cigarettes/day)" <?= ($lifestyle_info['smoking_status'] ?? '') === 'Moderate smoker (11-20 cigarettes/day)' ? 'selected' : '' ?>>Moderate smoker (11-20 cigarettes/day)</option>
                                <option value="Heavy smoker (20+ cigarettes/day)" <?= ($lifestyle_info['smoking_status'] ?? '') === 'Heavy smoker (20+ cigarettes/day)' ? 'selected' : '' ?>>Heavy smoker (20+ cigarettes/day)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Alcohol Intake</label>
                            <select name="alcohol_intake" class="form-select">
                                <option value="">Select</option>
                                <option value="Never" <?= ($lifestyle_info['alcohol_intake'] ?? '') === 'Never' ? 'selected' : '' ?>>Never</option>
                                <option value="Rarely (few times a year)" <?= ($lifestyle_info['alcohol_intake'] ?? '') === 'Rarely (few times a year)' ? 'selected' : '' ?>>Rarely (few times a year)</option>
                                <option value="Occasionally (1-2 times a month)" <?= ($lifestyle_info['alcohol_intake'] ?? '') === 'Occasionally (1-2 times a month)' ? 'selected' : '' ?>>Occasionally (1-2 times a month)</option>
                                <option value="Moderately (1-2 times a week)" <?= ($lifestyle_info['alcohol_intake'] ?? '') === 'Moderately (1-2 times a week)' ? 'selected' : '' ?>>Moderately (1-2 times a week)</option>
                                <option value="Frequently (3+ times a week)" <?= ($lifestyle_info['alcohol_intake'] ?? '') === 'Frequently (3+ times a week)' ? 'selected' : '' ?>>Frequently (3+ times a week)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Physical Activity</label>
                            <select name="physical_act" class="form-select">
                                <option value="">Select</option>
                                <option value="Sedentary (no regular exercise)" <?= ($lifestyle_info['physical_act'] ?? '') === 'Sedentary (no regular exercise)' ? 'selected' : '' ?>>Sedentary (no regular exercise)</option>
                                <option value="Light activity (1-2 times a week)" <?= ($lifestyle_info['physical_act'] ?? '') === 'Light activity (1-2 times a week)' ? 'selected' : '' ?>>Light activity (1-2 times a week)</option>
                                <option value="Moderate activity (3-4 times a week)" <?= ($lifestyle_info['physical_act'] ?? '') === 'Moderate activity (3-4 times a week)' ? 'selected' : '' ?>>Moderate activity (3-4 times a week)</option>
                                <option value="Active (5+ times a week)" <?= ($lifestyle_info['physical_act'] ?? '') === 'Active (5+ times a week)' ? 'selected' : '' ?>>Active (5+ times a week)</option>
                                <option value="Very active (daily exercise)" <?= ($lifestyle_info['physical_act'] ?? '') === 'Very active (daily exercise)' ? 'selected' : '' ?>>Very active (daily exercise)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Dietary Habits</label>
                            <select name="diet_habit" class="form-select">
                                <option value="">Select</option>
                                <option value="Poor (mostly fast food/processed)" <?= ($lifestyle_info['diet_habit'] ?? '') === 'Poor (mostly fast food/processed)' ? 'selected' : '' ?>>Poor (mostly fast food/processed)</option>
                                <option value="Fair (some healthy choices)" <?= ($lifestyle_info['diet_habit'] ?? '') === 'Fair (some healthy choices)' ? 'selected' : '' ?>>Fair (some healthy choices)</option>
                                <option value="Good (balanced diet most days)" <?= ($lifestyle_info['diet_habit'] ?? '') === 'Good (balanced diet most days)' ? 'selected' : '' ?>>Good (balanced diet most days)</option>
                                <option value="Excellent (very healthy, balanced)" <?= ($lifestyle_info['diet_habit'] ?? '') === 'Excellent (very healthy, balanced)' ? 'selected' : '' ?>>Excellent (very healthy, balanced)</option>
                                <option value="Vegetarian" <?= ($lifestyle_info['diet_habit'] ?? '') === 'Vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                                <option value="Vegan" <?= ($lifestyle_info['diet_habit'] ?? '') === 'Vegan' ? 'selected' : '' ?>>Vegan</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i> Save Lifestyle Information
                        </button>
                    </div>
                </form>
            </div>

            <!-- Custom popup for uneditable fields -->
            <div id="uneditablePopup" class="custom-modal" style="display:none;">
                <div class="custom-modal-content">
                    <h3>Field Not Editable Here</h3>
                    <p>This field cannot be edited in this form. To update this information, please contact the admin or go to User Settings.</p>
                    <div class="custom-modal-actions">
                        <button type="button" class="btn btn-secondary" id="closeUneditablePopup">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Snackbar logic
            <?php if (isset($_SESSION['snackbar_message'])) { ?>
                var snackbar = document.getElementById('snackbar');
                var snackbarText = document.getElementById('snackbar-text');
                if (snackbar && snackbarText) {
                    snackbarText.textContent = <?= json_encode($_SESSION['snackbar_message']) ?>;
                    snackbar.style.display = 'block';
                    setTimeout(function() {
                        snackbar.style.opacity = '1';
                    }, 100);
                    setTimeout(function() {
                        snackbar.style.opacity = '0';
                        setTimeout(function() {
                            snackbar.style.display = 'none';
                        }, 400);
                    }, 2500);
                }
            <?php unset($_SESSION['snackbar_message']);
            } ?>

            // Custom Back/Cancel modal logic
            const backBtn = document.getElementById('backCancelBtn');
            const modal = document.getElementById('backCancelModal');
            const modalCancel = document.getElementById('modalCancelBtn');
            const modalStay = document.getElementById('modalStayBtn');
            if (backBtn && modal && modalCancel && modalStay) {
                backBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                });
                
                modalCancel.addEventListener('click', function() {
                    modal.style.display = 'none';
                    window.location.href = "profile.php";
                });
                
                modalStay.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                // Close modal on outside click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) modal.style.display = 'none';
                });
            }

            // Uneditable fields popup logic
            const uneditablePopup = document.getElementById('uneditablePopup');
            const closeUneditablePopup = document.getElementById('closeUneditablePopup');
            
            if (uneditablePopup && closeUneditablePopup) {
                document.querySelectorAll('.readonly-field').forEach(function(field) {
                    field.addEventListener('focus', showUneditablePopup);
                    field.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        showUneditablePopup();
                    });
                });

                function showUneditablePopup() {
                    uneditablePopup.style.display = 'flex';
                }
                
                closeUneditablePopup.addEventListener('click', function() {
                    uneditablePopup.style.display = 'none';
                });
                
                uneditablePopup.addEventListener('click', function(e) {
                    if (e.target === uneditablePopup) uneditablePopup.style.display = 'none';
                });
            }

            // Enable Save Photo button only when a valid file is set
            const fileInput = document.getElementById('profilePhotoInput');
            const saveBtn = document.getElementById('savePhotoBtn');
            if (fileInput && saveBtn) {
                fileInput.addEventListener('change', function() {
                    saveBtn.disabled = !fileInput.files || !fileInput.files[0];
                });
            }
            // Prevent form submit if no file
            const photoForm = document.getElementById('profilePhotoForm');
            if (photoForm && saveBtn) {
                photoForm.addEventListener('submit', function(e) {
                    if (!fileInput.files || !fileInput.files[0]) {
                        e.preventDefault();
                        alert('Please select and crop a photo before saving.');
                    }
                });
            }

            // Conditional field visibility for patient status
            const pwdCheckbox = document.getElementById('pwdCheckbox');
            const pwdIdField = document.getElementById('pwdIdField');
            const philhealthCheckbox = document.getElementById('philhealthCheckbox');
            const philhealthFields = document.getElementById('philhealthFields');
            const seniorCheckbox = document.getElementById('seniorCheckbox');
            const seniorIdField = document.getElementById('seniorIdField');

            if (pwdCheckbox && pwdIdField) {
                pwdCheckbox.addEventListener('change', function() {
                    pwdIdField.style.display = this.checked ? 'block' : 'none';
                });
            }

            if (philhealthCheckbox && philhealthFields) {
                philhealthCheckbox.addEventListener('change', function() {
                    philhealthFields.style.display = this.checked ? 'block' : 'none';
                });
            }

            if (seniorCheckbox && seniorIdField) {
                seniorCheckbox.addEventListener('change', function() {
                    seniorIdField.style.display = this.checked ? 'block' : 'none';
                });
            }
        });
    </script>
</body>

</html>