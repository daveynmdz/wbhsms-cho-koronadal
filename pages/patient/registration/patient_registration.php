<?php // patient_registration.php 
session_start();

// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/env.php'; // Load database connection

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Load barangays from database
$barangays = [];
try {
    $stmt = $pdo->prepare("SELECT barangay_id, barangay_name FROM barangay WHERE status = 'active' ORDER BY barangay_name");
    $stmt->execute();
    $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Failed to load barangays: ' . $e->getMessage());
    // Fallback to hardcoded list if database fails
    $barangays = [
        ['barangay_id' => 1, 'barangay_name' => 'Brgy. Assumption'],
        ['barangay_id' => 2, 'barangay_name' => 'Brgy. Avanceña'],
        ['barangay_id' => 3, 'barangay_name' => 'Brgy. Cacub'],
        ['barangay_id' => 4, 'barangay_name' => 'Brgy. Caloocan'],
        ['barangay_id' => 5, 'barangay_name' => 'Brgy. Carpenter Hill'],
        ['barangay_id' => 6, 'barangay_name' => 'Brgy. Concepcion'],
        ['barangay_id' => 7, 'barangay_name' => 'Brgy. Esperanza'],
        ['barangay_id' => 8, 'barangay_name' => 'Brgy. General Paulino Santos'],
        ['barangay_id' => 9, 'barangay_name' => 'Brgy. Mabini'],
        ['barangay_id' => 10, 'barangay_name' => 'Brgy. Magsaysay'],
        ['barangay_id' => 11, 'barangay_name' => 'Brgy. Mambucal'],
        ['barangay_id' => 12, 'barangay_name' => 'Brgy. Morales'],
        ['barangay_id' => 13, 'barangay_name' => 'Brgy. Namnama'],
        ['barangay_id' => 14, 'barangay_name' => 'Brgy. New Pangasinan'],
        ['barangay_id' => 15, 'barangay_name' => 'Brgy. Paraiso'],
        ['barangay_id' => 16, 'barangay_name' => 'Brgy. Rotonda'],
        ['barangay_id' => 17, 'barangay_name' => 'Brgy. San Isidro'],
        ['barangay_id' => 18, 'barangay_name' => 'Brgy. San Roque'],
        ['barangay_id' => 19, 'barangay_name' => 'Brgy. San Jose'],
        ['barangay_id' => 20, 'barangay_name' => 'Brgy. Sta. Cruz'],
        ['barangay_id' => 21, 'barangay_name' => 'Brgy. Sto. Niño'],
        ['barangay_id' => 22, 'barangay_name' => 'Brgy. Saravia'],
        ['barangay_id' => 23, 'barangay_name' => 'Brgy. Topland'],
        ['barangay_id' => 24, 'barangay_name' => 'Brgy. Zone 1'],
        ['barangay_id' => 25, 'barangay_name' => 'Brgy. Zone 2'],
        ['barangay_id' => 26, 'barangay_name' => 'Brgy. Zone 3'],
        ['barangay_id' => 27, 'barangay_name' => 'Brgy. Zone 4']
    ];
}

// --- Error message and repopulation logic ---
$errorMsg = '';
if (isset($_SESSION['registration_error'])) {
    $errorMsg = $_SESSION['registration_error'];
    unset($_SESSION['registration_error']);
}
$formData = [
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'suffix' => '',
    'barangay' => '',
    'sex' => '',
    'dob' => '',
    'contact_num' => '',
    'email' => '',
    'isPWD' => false,
    'pwd_id_number' => '',
    'isPhilHealth' => false,
    'philhealth_type' => '',
    'philhealth_id_number' => '',
    'isSenior' => false,
    'senior_citizen_id' => '',
    'emergency_first_name' => '',
    'emergency_last_name' => '',
    'emergency_relationship' => '',
    'emergency_contact_number' => ''
];
if (isset($_SESSION['registration']) && is_array($_SESSION['registration'])) {
    foreach ($formData as $k => $v) {
        if (isset($_SESSION['registration'][$k])) {
            $formData[$k] = htmlspecialchars($_SESSION['registration'][$k], ENT_QUOTES, 'UTF-8');
        }
    }
    // Convert MM-DD-YYYY to YYYY-MM-DD for dob if needed
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $formData['dob'], $m)) {
        $formData['dob'] = $m[3] . '-' . $m[1] . '-' . $m[2];
    }
}
// Optionally clear registration session after repopulating
unset($_SESSION['registration']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO - Patient Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        /* ------------------ Base & Background ------------------ */
        :root {
            --brand: #007bff;
            --brand-600: #0056b3;
            --text: #03045e;
            --muted: #6c757d;
            --border: #ced4da;
            --surface: #ffffff;
            --shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            --focus-ring: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--text);
            background-image: url('https://ik.imagekit.io/wbhsmslogo/Blue%20Minimalist%20Background.png?updatedAt=1752410073954');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
            line-height: 1.5;
            min-height: 100vh;
        }

        /* ------------------ Header & Logo ------------------ */
        header {
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            background-image: url('https://ik.imagekit.io/wbhsmslogo/Blue%20Minimalist%20Background.png?updatedAt=1752410073954');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 14px 0;
        }

        .logo {
            width: 100px;
            height: auto;
            transition: transform 0.2s ease;
        }

        .logo:hover {
            transform: scale(1.04);
        }

        /* ------------------ Main Section ------------------ */
        .homepage {
            min-height: 100vh;
            display: grid;
            place-items: start center;
            padding: 160px 16px 40px;
        }

        @media (max-width: 768px) {
            .homepage {
                padding-top: 140px;
            }
        }

        @media (max-width: 480px) {
            .homepage {
                padding-top: 128px;
            }
        }

        /* ------------------ Registration Box ------------------ */
        .registration-box {
            width: 100%;
            min-width: 350px;
            max-width: 900px;
            background: var(--surface);
            border-radius: 16px;
            padding: 20px 22px 26px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative;
        }

        /* ------------------ Form Header ------------------ */
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .form-header h2 {
            margin: 0;
            font-size: 1.4rem;
            color: var(--text);
            text-align: center;
        }

        /* ------------------ Form Styling ------------------ */
        .form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        /* Labels */
        label {
            display: block;
            text-align: left;
            font-weight: 600;
            margin-bottom: 6px;
            margin-top: 2px;
            color: #333;
        }

        /* Input Fields */
        .input-field,
        select {
            height: 44px;
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            margin-bottom: 0;
        }

        .input-field::placeholder {
            color: #8a8f98;
        }

        .input-field:focus,
        select:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: var(--focus-ring);
        }

        /* Contact Number Input */
        .contact-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .contact-input-wrapper .prefix {
            position: absolute;
            left: 5px;
            font-size: 14px;
            color: #333;
            background: #f1f5f9;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 5px 7px;
            font-weight: 600;
        }

        .contact-number {
            padding-left: 48px;
            letter-spacing: 1px;
        }

        /* Password Toggle */
        .password-wrapper {
            position: relative;
            display: grid;
        }

        .password-wrapper .input-field {
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            top: 70%;
            right: 8px;
            transform: translateY(-50%);
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            color: #888;
        }

        .toggle-password:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        /* Password Requirements */
        .password-requirements {
            margin: 0 10px 0 0;
            padding-left: 20px;
            list-style: none;
            font-size: 0.95em;
            color: #555;
            margin-bottom: 20px;
            background: #f8fafc;
            border: 1px dashed var(--border);
            border-radius: 10px;
        }

        .password-requirements h4 {
            display: flex;
            text-align: left;
            align-items: center;
            margin-top: 4px;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .password-requirements li {
            margin-bottom: 4px;
            display: flex;
            text-align: left;
            align-items: center;
            gap: 8px;
        }

        .icon {
            color: red;
        }

        .icon.green {
            color: green;
        }

        .icon.red {
            color: red;
        }

        /* ------------------ Buttons ------------------ */
        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 10px;
            background-color: var(--brand);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease, background-color 0.12s ease;
        }

        .btn.secondary {
            background-color: #e5e7eb;
            color: #111827;
        }

        .btn:hover,
        .btn.secondary:hover {
            box-shadow: 0 6px 16px rgba(0, 123, 255, 0.25);
            background-color: var(--brand-600);
            transform: translateY(-1px);
        }

        .btn:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
        }

        .back-btn {
            background: none;
            border: none;
            color: var(--brand);
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            transition: color 0.2s;
        }

        .back-btn:hover {
            color: var(--brand-600);
        }

        /* ------------------ Modal Styles ------------------ */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal[open],
        .modal.show {
            display: flex !important;
        }

        .modal-content {
            background: #fff;
            width: min(720px, 92vw);
            margin: 0 auto;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .2);
            padding: 1.25rem 1.25rem 1rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .terms-text {
            margin: 20px 0;
            text-align: left;
            max-height: 55vh;
            overflow: auto;
            padding-right: .5rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .link-button {
            background: none;
            border: none;
            color: var(--brand);
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
        }

        /* ------------------ Error region ------------------ */
        .error {
            display: none;
            margin: .6rem 0;
            padding: .65rem .75rem;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        /* --- Responsive Grid for Form --- */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 24px;
            margin-bottom: 18px;
        }

        @media (max-width: 800px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 16px 0;
            }
        }

        /* --- Spacing for fields and labels --- */
        .grid>div,
        .form>div,
        .form>.password-wrapper,
        .form>.terms-checkbox {
            margin-bottom: 0;
        }

        /* --- Terms Checkbox Row --- */
        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 18px 0 0 0;
            font-size: 1rem;
            min-height: 44px;
        }

        .terms-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--brand);
            margin: 0 6px 0 0;
            flex-shrink: 0;
        }

        .terms-checkbox label {
            margin: 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Utility for grid item spanning two columns */
        .span-2 {
            grid-column: 1 / span 2;
        }

        @media (max-width: 800px) {
            .span-2 {
                grid-column: 1 / span 1;
            }
        }

        /* Make password requirements list full width in grid */
        .password-requirements {
            grid-column: 1 / span 2;
        }

        /* Additional Information Section */
        .additional-info-section {
            margin: 24px 0;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .additional-info-section h3 {
            margin: 0 0 16px 0;
            font-size: 1.1rem;
            color: var(--text);
            text-align: center;
            font-weight: 600;
        }

        /* Contact Information Section */
        .contact-info-section {
            margin: 24px 0;
            padding: 20px;
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 12px;
        }

        /* Emergency Contact Section */
        .emergency-contact-section {
            margin: 16px 0;
            padding: 16px;
            background: #fff7ed;
            border: 1px solid #fb923c;
            border-radius: 10px;
        }

        .emergency-contact-fields {
            margin-top: 8px;
        }

        .emergency-contact-fields .grid {
            margin-bottom: 0;
        }

        /* Checkbox Groups */
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 16px;
        }

        .checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .checkbox-item:hover {
            border-color: var(--brand);
        }

        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--brand);
            margin: 0;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .checkbox-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .checkbox-label {
            font-weight: 600;
            color: var(--text);
            margin: 0;
            font-size: 1rem;
        }

        .checkbox-description {
            font-size: 0.9rem;
            color: var(--muted);
            margin: 0;
        }

        /* Conditional Fields */
        .conditional-field {
            display: none;
            margin-top: 8px;
        }

        .conditional-field.show {
            display: block;
        }

        .conditional-field label {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .conditional-field .input-field,
        .conditional-field select {
            height: 36px;
            font-size: 0.9rem;
        }

        /* PhilHealth Type Specific */
        .philhealth-fields {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        @media (max-width: 600px) {
            .checkbox-item {
                padding: 10px;
            }
            
            .checkbox-group {
                gap: 12px;
            }
        }

        /* ------------------ Loading Overlay ------------------ */
        .logo {
            transition: none;
        }

        .btn,
        .input-field {
            transition: none;
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            color: var(--brand, #0d6efd);
        }

        .loading-card {
            text-align: center;
        }

        .loading-card .logo {
            width: 96px;
            height: auto;
            display: block;
            margin: 0 auto 14px;
        }

        .loading-card .title {
            font-size: 1.05rem;
            margin: 0 0 8px;
            font-weight: 700;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo-container">
            <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128"
                alt="CHO Koronadal Logo" />
        </div>
    </header>

    <section class="homepage">
        <div id="loading" class="loading-overlay hidden" aria-live="polite" aria-busy="true">
            <div class="loading-card">
                <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128"
                    alt="CHO Koronadal Logo" />
                <p class="title">Validating and checking for duplicates…</p>
                <p><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i></p>
            </div>
        </div>
        <div class="registration-box">
            <h2>Patient Account Registration</h2>

            <div class="form-header">
                <button type="button" class="btn secondary" onclick="window.location.href='../auth/patient_login.php'">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </button>
            </div>

            <!-- Live error region moved below, just above submit button -->

            <form id="registrationForm" action="register_patient.php" method="POST">
                <!-- CSRF placeholder (server should populate) -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" />

                <div id="error" class="error" role="alert" aria-live="polite"
                    style="display:<?php echo $errorMsg !== '' ? 'block' : 'none'; ?>" tabindex="-1">
                    <?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class=" grid">
                    <div>
                        <label for="barangay">Barangay*</label>
                        <select id="barangay" name="barangay" class="input-field" required>
                            <option value="" disabled <?php echo $formData['barangay'] === '' ? 'selected' : '' ?>>Select your barangay</option>
                            <?php
                            foreach ($barangays as $brgy) {
                                $selected = ($formData['barangay'] === $brgy['barangay_name']) ? 'selected' : '';
                                $label = htmlspecialchars($brgy['barangay_name'], ENT_QUOTES, 'UTF-8');
                                echo '<option value="' . $label . '" ' . $selected . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label for="last-name">Last Name*</label>
                        <input type="text" id="last-name" name="last_name" class="input-field" required autocomplete="family-name" value="<?php echo $formData['last_name']; ?>" />
                    </div>

                    <div>
                        <label for="first-name">First Name*</label>
                        <input type="text" id="first-name" name="first_name" class="input-field" required autocomplete="given-name" value="<?php echo $formData['first_name']; ?>" />
                    </div>

                    <div>
                        <label for="middle-name">Middle Name</label>
                        <input type="text" id="middle-name" name="middle_name" class="input-field" autocomplete="additional-name" value="<?php echo $formData['middle_name']; ?>" />
                    </div>

                    <div>
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" placeholder="e.g. Jr., Sr., II, III" class="input-field" value="<?php echo $formData['suffix']; ?>" />
                    </div>

                    <div>
                        <label for="sex">Sex*</label>
                        <select id="sex" name="sex" class="input-field" required>
                            <option value="" disabled <?php echo $formData['sex'] === '' ? 'selected' : '' ?>>Select if Male or Female</option>
                            <option value="Male" <?php echo ($formData['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($formData['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div>
                        <label for="dob">Date of Birth*</label>
                        <input type="date" id="dob" name="dob" class="input-field" required
                            min="1900-01-01" max="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($formData['dob'] ?? '', ENT_QUOTES); ?>" />
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="additional-info-section">
                    <h3>Additional Information</h3>
                    <div class="checkbox-group">
                        <!-- PWD Section -->
                        <div class="checkbox-item">
                            <input type="checkbox" id="isPWD" name="isPWD" value="1" <?php echo $formData['isPWD'] ? 'checked' : ''; ?> />
                            <div class="checkbox-content">
                                <label for="isPWD" class="checkbox-label">Person with Disability (PWD)</label>
                                <p class="checkbox-description">Check this if you are a registered person with disability</p>
                                <div class="conditional-field" id="pwd-field">
                                    <label for="pwd_id_number">PWD ID Number</label>
                                    <input type="text" id="pwd_id_number" name="pwd_id_number" class="input-field" 
                                           placeholder="Enter PWD ID Number" value="<?php echo $formData['pwd_id_number']; ?>" />
                                </div>
                            </div>
                        </div>

                        <!-- PhilHealth Section -->
                        <div class="checkbox-item">
                            <input type="checkbox" id="isPhilHealth" name="isPhilHealth" value="1" <?php echo $formData['isPhilHealth'] ? 'checked' : ''; ?> />
                            <div class="checkbox-content">
                                <label for="isPhilHealth" class="checkbox-label">PhilHealth Member</label>
                                <p class="checkbox-description">Check this if you have PhilHealth coverage</p>
                                <div class="conditional-field philhealth-fields" id="philhealth-fields">
                                    <div>
                                        <label for="philhealth_type">Membership Type</label>
                                        <select id="philhealth_type" name="philhealth_type" class="input-field">
                                            <option value="">Select Type</option>
                                            <option value="Member" <?php echo $formData['philhealth_type'] === 'Member' ? 'selected' : ''; ?>>Member</option>
                                            <option value="Beneficiary" <?php echo $formData['philhealth_type'] === 'Beneficiary' ? 'selected' : ''; ?>>Beneficiary</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="philhealth_id_number">PhilHealth ID Number</label>
                                        <input type="text" id="philhealth_id_number" name="philhealth_id_number" class="input-field" 
                                               placeholder="Enter PhilHealth ID (12 digits)" maxlength="12" 
                                               value="<?php echo $formData['philhealth_id_number']; ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Senior Citizen Section (auto-shown if 60+ years old) -->
                        <div class="checkbox-item" id="senior-citizen-section" style="display: none;">
                            <input type="checkbox" id="isSenior" name="isSenior" value="1" <?php echo $formData['isSenior'] ? 'checked' : ''; ?> />
                            <div class="checkbox-content">
                                <label for="isSenior" class="checkbox-label">Senior Citizen</label>
                                <p class="checkbox-description">You are eligible for Senior Citizen benefits (60+ years old)</p>
                                <div class="conditional-field" id="senior-field">
                                    <label for="senior_citizen_id">Senior Citizen ID</label>
                                    <input type="text" id="senior_citizen_id" name="senior_citizen_id" class="input-field" 
                                           placeholder="Enter Senior Citizen ID" value="<?php echo $formData['senior_citizen_id']; ?>" />
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact Section (auto-shown if under 18 years old) -->
                        <div class="emergency-contact-section" id="emergency-contact-section" style="display: none;">
                            <div class="checkbox-content">
                                <label class="checkbox-label">Emergency Contact (Parent/Guardian)</label>
                                <p class="checkbox-description">Required for patients under 18 years old</p>
                                <div class="emergency-contact-fields">
                                    <div class="grid" style="margin-bottom: 0;">
                                        <div>
                                            <label for="emergency_first_name">Guardian First Name*</label>
                                            <input type="text" id="emergency_first_name" name="emergency_first_name" class="input-field" 
                                                   placeholder="Enter guardian's first name" value="<?php echo $formData['emergency_first_name']; ?>" />
                                        </div>
                                        <div>
                                            <label for="emergency_last_name">Guardian Last Name*</label>
                                            <input type="text" id="emergency_last_name" name="emergency_last_name" class="input-field" 
                                                   placeholder="Enter guardian's last name" value="<?php echo $formData['emergency_last_name']; ?>" />
                                        </div>
                                        <div>
                                            <label for="emergency_relationship">Relationship*</label>
                                            <select id="emergency_relationship" name="emergency_relationship" class="input-field">
                                                <option value="">Select Relationship</option>
                                                <option value="Mother" <?php echo $formData['emergency_relationship'] === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                                                <option value="Father" <?php echo $formData['emergency_relationship'] === 'Father' ? 'selected' : ''; ?>>Father</option>
                                                <option value="Guardian" <?php echo $formData['emergency_relationship'] === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                                                <option value="Grandmother" <?php echo $formData['emergency_relationship'] === 'Grandmother' ? 'selected' : ''; ?>>Grandmother</option>
                                                <option value="Grandfather" <?php echo $formData['emergency_relationship'] === 'Grandfather' ? 'selected' : ''; ?>>Grandfather</option>
                                                <option value="Aunt" <?php echo $formData['emergency_relationship'] === 'Aunt' ? 'selected' : ''; ?>>Aunt</option>
                                                <option value="Uncle" <?php echo $formData['emergency_relationship'] === 'Uncle' ? 'selected' : ''; ?>>Uncle</option>
                                                <option value="Other" <?php echo $formData['emergency_relationship'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="emergency_contact_number">Guardian Contact No.*</label>
                                            <div class="contact-input-wrapper">
                                                <span class="prefix">+63</span>
                                                <input type="tel" id="emergency_contact_number" name="emergency_contact_number" 
                                                       class="input-field contact-number" placeholder="### ### ####" maxlength="13" 
                                                       inputmode="numeric" value="<?php echo $formData['emergency_contact_number']; ?>" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="contact-info-section">
                    <div class="grid">
                        <div>
                            <label for="contact-number">Contact No.*</label>
                            <div class="contact-input-wrapper">
                                <span class="prefix">+63</span>
                                <input type="tel" id="contact-number" name="contact_num" class="input-field contact-number" placeholder="### ### ####" maxlength="13" inputmode="numeric" autocomplete="tel-national" required value="<?php echo $formData['contact_num']; ?>" />
                            </div>
                        </div>

                        <div>
                            <label for="email">Email*</label>
                            <input type="email" id="email" name="email" class="input-field" required autocomplete="email" value="<?php echo $formData['email']; ?>" />
                        </div>

                        <div class="password-wrapper">
                            <label for="password">Password*</label>
                            <input type="password" id="password" name="password" class="input-field" required autocomplete="new-password" aria-describedby="pw-req" />
                            <i class="fa-solid fa-eye toggle-password" role="button" tabindex="0" aria-label="Toggle password visibility" aria-hidden="true"></i>
                        </div>

                        <div class="password-wrapper">
                            <label for="confirm-password">Confirm Password*</label>
                            <input type="password" id="confirm-password" name="confirm_password" class="input-field" required autocomplete="new-password" />
                            <i class="fa-solid fa-eye toggle-password" aria-hidden="true"></i>
                        </div>
                    </div>

                    <div class="password-requirements-wrapper">
                        <h4 id="pw-req" style="text-align: justify;">Password Requirements:</h4>
                        <ul class="password-requirements" id="password-requirements">
                            <li id="length"><i class="fa-solid fa-circle-xmark icon red"></i> At least 8 characters</li>
                            <li id="uppercase"><i class="fa-solid fa-circle-xmark icon red"></i> At least one uppercase letter
                            </li>
                            <li id="lowercase"><i class="fa-solid fa-circle-xmark icon red"></i> At least one lowercase letter
                            </li>
                            <li id="number"><i class="fa-solid fa-circle-xmark icon red"></i> At least one number</li>
                            <li id="match"><i class="fa-solid fa-circle-xmark icon red"></i> Passwords match</li>
                        </ul>
                    </div>
                </div>

                <div class="terms-checkbox">
                    <input type="checkbox" id="terms-check" name="agree_terms" required />
                    <label for="terms-check">
                        I agree to the
                        <button type="button" id="show-terms" class="link-button">Terms &amp; Conditions</button>
                    </label>
                </div>

                <div class="form-footer">
                    <button id="submitBtn" type="submit" class="btn">Submit <i
                            class="fa-solid fa-arrow-right"></i></button>
                </div>
            </form>
        </div>
    </section>

    <!-- Terms Modal -->
    <div id="terms-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="terms-title">
        <div class="modal-content">
            <h2 id="terms-title">Terms &amp; Conditions</h2>
            <div class="terms-text">
                <h3>CHO Koronadal - Patient Terms and Conditions</h3>
                <p>Welcome to the City Health Office of Koronadal. By registering, you agree to provide accurate and
                    truthful information. Your data will be used solely for healthcare management purposes and will be
                    kept confidential in accordance with our privacy policy. Misuse of the system or providing false
                    information may result in account suspension or legal action. For more details, please contact the
                    City Health Office.</p>
                <p>1. By using this service, you agree...</p>
                <p>2. Your responsibilities include...</p>
                <p>3. Data privacy and security...</p>
            </div>
            <div class="modal-buttons">
                <button id="disagree-btn" class="btn secondary">I Do Not Agree</button>
                <button id="agree-btn" class="btn">I Agree</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var loading = document.getElementById('loading');
            if (!loading) return;

            function show() {
                loading.classList.remove('hidden');
            }
            // Prefer #registrationForm, fallback to first <form>
            var form = document.getElementById('registrationForm') || document.querySelector('form');
            if (!form) return;
            form.addEventListener('submit', function() {
                show();
            });
        })();
        // ===== UTILITIES =====
        const $ = (sel, ctx = document) => ctx.querySelector(sel);
        const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

        /*// --- Pre-fill registration form from sessionStorage if available ---
        window.addEventListener('DOMContentLoaded', () => {
            try {
                const data = JSON.parse(sessionStorage.getItem('registrationData'));
                if (data) {
                    if (data.last_name) $('#last-name').value = data.last_name;
                    if (data.first_name) $('#first-name').value = data.first_name;
                    if (data.middle_name) $('#middle-name').value = data.middle_name;
                    if (data.suffix) $('#suffix').value = data.suffix;
                    if (data.barangay) $('#barangay').value = data.barangay;
                    if (data.sex) $('#sex').value = data.sex;
                    if (data.dob) $('#dob').value = data.dob;
                    if (data.contact_num) $('#contact-number').value = data.contact_num;
                    if (data.email) $('#email').value = data.email;
                }
            } catch (_) {}
        });*/

        // --- Password toggle: add aria-labels and delegated handling ---
        document.addEventListener('click', (e) => {
            const icon = e.target.closest('.toggle-password');
            if (!icon) return;
            // ensure ARIA label exists
            if (!icon.hasAttribute('aria-label')) {
                icon.setAttribute('aria-label', 'Toggle password visibility');
                icon.setAttribute('role', 'button');
                icon.setAttribute('tabindex', '0');
            }
            const input = icon.previousElementSibling;
            if (!input) return;
            const newType = input.type === 'password' ? 'text' : 'password';
            input.type = newType;
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // --- Contact_Num formatter + validation (PH mobile without leading 0; prefix +63) ---
        const contact_num = $('#contact-number');
        contact_num.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.startsWith('0')) value = value.substring(1); // remove leading 0
            if (value.length > 10) value = value.slice(0, 10);
            const formatted =
                value.substring(0, 3) +
                (value.length > 3 ? ' ' + value.substring(3, 6) : '') +
                (value.length > 6 ? ' ' + value.substring(6, 10) : '');
            this.value = formatted.trim();
        });

        // --- Password requirements live checker (NO special char requirement per user's request) ---
        const pw = $('#password');
        const confirmPw = $('#confirm-password');
        const reqs = {
            length: (v) => v.length >= 8,
            uppercase: (v) => /[A-Z]/.test(v),
            lowercase: (v) => /[a-z]/.test(v),
            number: (v) => /[0-9]/.test(v),
        };
        const updateReq = (li, ok) => {
            const icon = li.querySelector('i');
            if (ok) {
                icon.classList.remove('fa-circle-xmark', 'red');
                icon.classList.add('fa-circle-check', 'green');
            } else {
                icon.classList.remove('fa-circle-check', 'green');
                icon.classList.add('fa-circle-xmark', 'red');
            }
        };

        function updateAllPwReqs() {
            const v = pw.value;
            updateReq($('#length'), reqs.length(v));
            updateReq($('#uppercase'), reqs.uppercase(v));
            updateReq($('#lowercase'), reqs.lowercase(v));
            updateReq($('#number'), reqs.number(v));
            updateReq($('#match'), v && v === confirmPw.value && confirmPw.value.length > 0);
        }
        pw.addEventListener('input', updateAllPwReqs);
        confirmPw.addEventListener('input', updateAllPwReqs);

        // --- Conditional fields for additional information ---
        const pwdCheckbox = $('#isPWD');
        const pwdField = $('#pwd-field');
        const philhealthCheckbox = $('#isPhilHealth');
        const philhealthFields = $('#philhealth-fields');
        const seniorCheckbox = $('#isSenior');
        const seniorField = $('#senior-field');
        const seniorSection = $('#senior-citizen-section');
        const emergencySection = $('#emergency-contact-section');

        // Age calculation function
        function calculateAge(birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            
            return age;
        }

        // Check age and show appropriate sections
        function checkAgeAndShowSections() {
            const dobValue = $('#dob').value;
            
            if (!dobValue) {
                // Hide both sections if no DOB
                seniorSection.style.display = 'none';
                emergencySection.style.display = 'none';
                return;
            }
            
            const age = calculateAge(dobValue);
            
            // Show Senior Citizen section if 60 or older
            if (age >= 60) {
                seniorSection.style.display = 'block';
                emergencySection.style.display = 'none';
            }
            // Show Emergency Contact section if under 18
            else if (age < 18) {
                emergencySection.style.display = 'block';
                seniorSection.style.display = 'none';
                // Make emergency contact fields required
                $('#emergency_first_name').required = true;
                $('#emergency_last_name').required = true;
                $('#emergency_relationship').required = true;
                $('#emergency_contact_number').required = true;
            }
            // Hide both sections if age is between 18-59
            else {
                seniorSection.style.display = 'none';
                emergencySection.style.display = 'none';
                // Remove required from emergency contact fields
                $('#emergency_first_name').required = false;
                $('#emergency_last_name').required = false;
                $('#emergency_relationship').required = false;
                $('#emergency_contact_number').required = false;
            }
        }

        // PWD conditional field
        function togglePwdField() {
            if (pwdCheckbox.checked) {
                pwdField.classList.add('show');
            } else {
                pwdField.classList.remove('show');
                $('#pwd_id_number').value = '';
            }
        }

        // PhilHealth conditional fields
        function togglePhilhealthFields() {
            if (philhealthCheckbox.checked) {
                philhealthFields.classList.add('show');
            } else {
                philhealthFields.classList.remove('show');
                $('#philhealth_type').value = '';
                $('#philhealth_id_number').value = '';
            }
        }

        // Senior Citizen conditional field
        function toggleSeniorField() {
            if (seniorCheckbox.checked) {
                seniorField.classList.add('show');
            } else {
                seniorField.classList.remove('show');
                $('#senior_citizen_id').value = '';
            }
        }

        // Emergency contact number formatter (same as main contact)
        const emergencyContactInput = $('#emergency_contact_number');
        if (emergencyContactInput) {
            emergencyContactInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.startsWith('0')) value = value.substring(1); // remove leading 0
                if (value.length > 10) value = value.slice(0, 10);
                const formatted =
                    value.substring(0, 3) +
                    (value.length > 3 ? ' ' + value.substring(3, 6) : '') +
                    (value.length > 6 ? ' ' + value.substring(6, 10) : '');
                this.value = formatted.trim();
            });
        }

        // Initial setup and event listeners
        pwdCheckbox.addEventListener('change', togglePwdField);
        philhealthCheckbox.addEventListener('change', togglePhilhealthFields);
        seniorCheckbox.addEventListener('change', toggleSeniorField);
        
        // Add DOB change listener for age-based sections
        $('#dob').addEventListener('change', checkAgeAndShowSections);

        // Initialize conditional fields on page load
        togglePwdField();
        togglePhilhealthFields();
        toggleSeniorField();
        checkAgeAndShowSections();

        // PhilHealth ID formatting (numbers only)
        const philhealthIdInput = $('#philhealth_id_number');
        philhealthIdInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 12) value = value.slice(0, 12);
            this.value = value;
        });

        // --- Terms modal wiring ---
        const termsModal = $('#terms-modal');
        const showTermsBtn = $('#show-terms');
        const agreeBtn = $('#agree-btn');
        const disagreeBtn = $('#disagree-btn');
        const termsCheck = $('#terms-check');
        const submitBtn = $('#submitBtn');

        showTermsBtn.addEventListener('click', () => {
            termsModal.classList.add('show');
        });
        agreeBtn.addEventListener('click', () => {
            termsModal.classList.remove('show');
            termsCheck.checked = true;
            submitBtn.disabled = false;
        });
        disagreeBtn.addEventListener('click', () => {
            termsModal.classList.remove('show');
            termsCheck.checked = false;
            submitBtn.disabled = true;
        });
        window.addEventListener('click', (e) => {
            if (e.target === termsModal) termsModal.classList.remove('show');
        });

        // --- DOB guardrails (no future, not older than 120 years) ---
        const dobInput = $('#dob'); // was "dob"
        const fmtLocal = (d) => {
            const p = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        };
        const setDobBounds = () => {
            const today = new Date();
            const max = fmtLocal(today); // local date, not UTC ISO
            const min = fmtLocal(new Date(today.getFullYear() - 120, today.getMonth(), today.getDate()));
            dobInput.max = max;
            dobInput.min = min;
        };
        setDobBounds();


        // --- Accessibility: make error region focusable so error.focus() works ---
        const error = $('#error');
        if (error && !error.hasAttribute('tabindex')) {
            error.setAttribute('tabindex', '-1');
        }

        // --- Barangay whitelist & UX improvement ---
        const validBarangays = new Set([
            <?php 
            $jsBarangays = array_map(function($brgy) {
                return "'" . addslashes($brgy['barangay_name']) . "'";
            }, $barangays);
            echo implode(', ', $jsBarangays);
            ?>
        ]);
        const barangaySelect = $('#barangay');
        barangaySelect.addEventListener('change', function() {
            // disable the placeholder option (value is empty)
            const placeholder = this.querySelector('option[value=""]');
            if (placeholder) placeholder.disabled = true;
        });

        // --- Utilities for error display ---
        function showError(msg) {
            error.textContent = msg;
            error.style.display = 'block';
            setTimeout(() => {
                error.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                error.focus && error.focus();
            }, 50);
        }

        function clearError() {
            error.textContent = '';
            error.style.display = 'none';
        }

        // --- Normalization helpers ---
        function capitalizeWords(str) {
            return str
                .toLowerCase()
                .split(/\s+/)
                .filter(Boolean)
                .map(s => s.charAt(0).toUpperCase() + s.slice(1))
                .join(' ');
        }

        // --- Form submission handling ---
        const loading = document.getElementById('loading');
        const regForm = $('#registrationForm');
        let isSubmitting = false;

        regForm.addEventListener('submit', (e) => {
            clearError();

            if (isSubmitting) {
                e.preventDefault();
                return;
            }

            function fail(msg) {
                e.preventDefault();
                if (loading) loading.classList.add('hidden');
                showError(msg);
            }

            // Basic requireds (IDs must match)
            const requiredIds = ['last-name', 'first-name', 'barangay', 'sex', 'dob', 'contact-number', 'email', 'password', 'confirm-password'];
            for (const id of requiredIds) {
                const el = document.getElementById(id);
                if (!el || !el.value) {
                    e.preventDefault();
                    showError('Please fill in all required fields.');
                    return;
                }
            }

            // Terms
            if (!termsCheck.checked) {
                e.preventDefault();
                return showError('You must agree to the Terms & Conditions.');
            }

            // Barangay valid
            const brgy = $('#barangay').value;
            if (!validBarangays.has(brgy)) {
                e.preventDefault();
                return showError('Please select a valid barangay.');
            }

            // DOB guard (string compare against min/max avoids timezone issues)
            if (dobInput.value) {
                if (dobInput.min && dobInput.value < dobInput.min) {
                    e.preventDefault();
                    return showError('Please enter a valid date of birth.');
                }
                if (dobInput.max && dobInput.value > dobInput.max) {
                    e.preventDefault();
                    return showError('Please enter a valid date of birth.');
                }
            }


            // Contact_Num: ensure 10 digits and starts with 9 (PH mobile)
            const digits = $('#contact-number').value.replace(/\D/g, '');
            if (!/^[0-9]{10}$/.test(digits)) {
                e.preventDefault();
                return showError('Contact number must be 10 digits (e.g., 912 345 6789).');
            }
            if (!/^9\d{9}$/.test(digits)) {
                e.preventDefault();
                return showError('Contact number must start with 9 (PH mobile numbers).');
            }

            // Additional information validation
            // PWD validation
            if (pwdCheckbox.checked) {
                const pwdId = $('#pwd_id_number').value.trim();
                if (!pwdId) {
                    e.preventDefault();
                    return showError('PWD ID Number is required when PWD is checked.');
                }
            }

            // PhilHealth validation
            if (philhealthCheckbox.checked) {
                const philhealthType = $('#philhealth_type').value;
                const philhealthId = $('#philhealth_id_number').value.trim();
                
                if (!philhealthType) {
                    e.preventDefault();
                    return showError('PhilHealth membership type is required when PhilHealth is checked.');
                }
                
                if (!philhealthId) {
                    e.preventDefault();
                    return showError('PhilHealth ID Number is required when PhilHealth is checked.');
                }
                
                // PhilHealth ID should be 12 digits
                const philhealthDigits = philhealthId.replace(/\D/g, '');
                if (philhealthDigits.length !== 12) {
                    e.preventDefault();
                    return showError('PhilHealth ID must be 12 digits.');
                }
            }

            // Age-based validation
            const dobValue = dobInput.value;
            if (dobValue) {
                const age = calculateAge(dobValue);
                
                // Senior Citizen validation (age-based, not checkbox-based)
                if (age >= 60 && seniorCheckbox.checked) {
                    const seniorId = $('#senior_citizen_id').value.trim();
                    if (!seniorId) {
                        e.preventDefault();
                        return showError('Senior Citizen ID is required when Senior Citizen is checked.');
                    }
                }
                
                // Emergency contact validation for minors
                if (age < 18) {
                    const emergencyFirstName = $('#emergency_first_name').value.trim();
                    const emergencyLastName = $('#emergency_last_name').value.trim();
                    const emergencyRelationship = $('#emergency_relationship').value;
                    const emergencyContact = $('#emergency_contact_number').value.replace(/\D/g, '');
                    
                    if (!emergencyFirstName) {
                        e.preventDefault();
                        return showError('Guardian first name is required for patients under 18.');
                    }
                    
                    if (!emergencyLastName) {
                        e.preventDefault();
                        return showError('Guardian last name is required for patients under 18.');
                    }
                    
                    if (!emergencyRelationship) {
                        e.preventDefault();
                        return showError('Guardian relationship is required for patients under 18.');
                    }
                    
                    if (!emergencyContact || emergencyContact.length !== 10 || !emergencyContact.startsWith('9')) {
                        e.preventDefault();
                        return showError('Valid guardian contact number is required for patients under 18.');
                    }
                }
            }

            // Email basic pattern & normalize to lowercase
            const emailEl = $('#email');
            emailEl.value = emailEl.value.trim().toLowerCase();
            const email = emailEl.value;
            const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
            if (!emailOk) {
                e.preventDefault();
                return showError('Please enter a valid email address.');
            }

            // Password rules (match the visual checker)
            const p1 = pw.value;
            const p2 = confirmPw.value;
            const ok = reqs.length(p1) && reqs.uppercase(p1) && reqs.lowercase(p1) && reqs.number(p1);
            if (!ok) {
                e.preventDefault();
                return showError('Password must be at least 8 chars with uppercase, lowercase, and a number.');
            }
            if (p1 !== p2) {
                e.preventDefault();
                return showError('Passwords do not match.');
            }

            // Normalize & trim a few fields before storing/submitting
            $('#last-name').value = capitalizeWords($('#last-name').value.trim());
            $('#first-name').value = capitalizeWords($('#first-name').value.trim());
            $('#middle-name').value = capitalizeWords($('#middle-name').value.trim());
            $('#suffix').value = $('#suffix').value.trim().toUpperCase(); // keep suffix uppercase
            // store contact as digits only (server should expect this)
            $('#contact-number').value = digits;

            // Normalize emergency contact fields if they exist and are visible
            const emergencyFirstNameEl = $('#emergency_first_name');
            const emergencyLastNameEl = $('#emergency_last_name');
            const emergencyContactEl = $('#emergency_contact_number');
            
            if (emergencyFirstNameEl && emergencyFirstNameEl.value) {
                emergencyFirstNameEl.value = capitalizeWords(emergencyFirstNameEl.value.trim());
            }
            if (emergencyLastNameEl && emergencyLastNameEl.value) {
                emergencyLastNameEl.value = capitalizeWords(emergencyLastNameEl.value.trim());
            }
            if (emergencyContactEl && emergencyContactEl.value) {
                const emergencyDigits = emergencyContactEl.value.replace(/\D/g, '');
                emergencyContactEl.value = emergencyDigits;
            }

            // Optional: store non-sensitive fields in sessionStorage
            const registrationData = {
                last_name: $('#last-name').value,
                first_name: $('#first-name').value,
                middle_name: $('#middle-name').value,
                suffix: $('#suffix').value,
                barangay: $('#barangay').value,
                sex: $('#sex').value,
                dob: $('#dob').value,
                contact_num: $('#contact-number').value,
                email: $('#email').value,
                isPWD: pwdCheckbox.checked ? 1 : 0,
                pwd_id_number: pwdCheckbox.checked ? $('#pwd_id_number').value : '',
                isPhilHealth: philhealthCheckbox.checked ? 1 : 0,
                philhealth_type: philhealthCheckbox.checked ? $('#philhealth_type').value : '',
                philhealth_id_number: philhealthCheckbox.checked ? $('#philhealth_id_number').value : '',
                isSenior: seniorCheckbox.checked ? 1 : 0,
                senior_citizen_id: seniorCheckbox.checked ? $('#senior_citizen_id').value : '',
                emergency_first_name: emergencyFirstNameEl ? emergencyFirstNameEl.value : '',
                emergency_last_name: emergencyLastNameEl ? emergencyLastNameEl.value : '',
                emergency_relationship: $('#emergency_relationship') ? $('#emergency_relationship').value : '',
                emergency_contact_number: emergencyContactEl ? emergencyContactEl.value : ''
            };
            try {
                sessionStorage.setItem('registrationData', JSON.stringify(registrationData));
            } catch (_) {}
            if (loading) loading.classList.remove('hidden');

            // Double-submit guard + loading indicator
            isSubmitting = true;
            submitBtn.disabled = true;
            const originalBtnHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Submitting... <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';

            // Allow native submit to proceed. If you want to re-enable on client-side failure later,
            // make sure to set isSubmitting = false and restore submitBtn.innerHTML = originalBtnHTML;
        });
    </script>
</body>

</html>