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
            padding: 20px 35px 26px;
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

        /* Input validation states */
        .input-field.valid {
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
        }

        .input-field.invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
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
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .modal[open],
        .modal.show {
            display: flex !important;
            opacity: 1;
        }

        .modal-content {
            background: #fff;
            width: min(800px, 95vw);
            margin: 0 auto;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 0;
            max-height: 85vh;
            overflow: hidden;
            transform: scale(0.9);
            transition: transform 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-content h2 {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-600) 100%);
            color: white;
            margin: 0;
            padding: 24px 32px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.2);
        }

        .terms-text {
            margin: 0;
            padding: 32px;
            text-align: left;
            max-height: 55vh;
            overflow-y: auto;
            line-height: 1.6;
            color: var(--text);
        }

        .terms-text::-webkit-scrollbar {
            width: 8px;
        }

        .terms-text::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .terms-text::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        .terms-text::-webkit-scrollbar-thumb:hover {
            background: var(--brand);
        }

        .terms-text h3 {
            color: var(--brand);
            font-size: 1.3rem;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--brand);
            font-weight: 600;
        }

        .terms-text p {
            margin: 16px 0;
            text-align: justify;
            color: #4a5568;
            font-size: 0.95rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 32px;
            background: #f8fafc;
            border-radius: 0 0 20px 20px;
            border-top: 1px solid var(--border);
        }

        .modal-buttons .btn {
            flex: 1;
            max-width: 180px;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .modal-buttons .btn.secondary {
            background: #e2e8f0;
            color: #4a5568;
            border: 2px solid #cbd5e0;
        }

        .modal-buttons .btn.secondary:hover {
            background: #cbd5e0;
            color: #2d3748;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .modal-buttons .btn:not(.secondary) {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-600) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .modal-buttons .btn:not(.secondary):hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }

        /* Modal Animation */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal.show .modal-content {
            animation: modalFadeIn 0.3s ease forwards;
        }

        .link-button {
            background: none;
            border: none;
            color: var(--brand);
            text-decoration: none;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s ease;
            position: relative;
        }

        .link-button:hover {
            color: var(--brand-600);
            background: rgba(0, 123, 255, 0.08);
            text-decoration: underline;
        }

        .link-button:focus {
            outline: 2px solid var(--brand);
            outline-offset: 2px;
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
            gap: 12px;
            margin: 24px 0 0 0;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            min-height: 44px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .terms-checkbox:hover {
            border-color: var(--brand);
        }

        .terms-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--brand);
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .terms-checkbox input[type="checkbox"]:hover {
            transform: scale(1.1);
        }

        .terms-checkbox label {
            margin: 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: var(--text);
            line-height: 1.5;
        }

        /* --- Form Footer Section --- */
        .form-footer {
            margin: 24px 0 0 0;
            padding: 24px;
            text-align: center;
        }

        .form-footer .btn {
            min-width: 160px;
            padding: 12px 24px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-600) 100%);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
            transition: all 0.2s ease;
        }

        .form-footer .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }

        .form-footer .btn:active {
            transform: translateY(0);
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
            text-align: justify;

        }

        /* Contact Information Section */
        .contact-info-section {
            margin: 24px 0;
            padding: 20px;
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 12px;
        }

        /* Patient Information Section */
        .patient-info-section {
            margin: 24px 0;
            padding: 20px;
            background: #f0fdf4;
            border: 1px solid #22c55e;
            border-radius: 12px;
        }

        .patient-info-section h3 {
            color: #16a34a;
            font-size: 1.2rem;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 8px;
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

        /* Override for Emergency Contact Section - not a checkbox item */
        .emergency-contact-section {
            display: none;
            /* Hidden by default */
        }

        .emergency-contact-section.show {
            display: block !important;
            /* Show when needed */
        }

        /* Override for Senior Citizen Section when shown based on age */
        #senior-citizen-section {
            display: none;
            /* Hidden by default */
        }

        #senior-citizen-section.show {
            display: flex !important;
            /* Show as flex to maintain checkbox-item layout */
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
            text-align: justify;
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

        /* PWD ID Field Styling */
        .pwd-id-wrapper {
            position: relative;
        }

        .pwd-prefix {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
            pointer-events: none;
            z-index: 2;
        }

        .pwd-id-input {
            padding-left: 140px !important;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .pwd-instructions {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #0369a1;
            text-align: justify;
        }

        .pwd-sample {
            font-family: 'Courier New', monospace;
            background: #f0f9ff;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #0ea5e9;
            display: inline-block;
            margin: 4px 0;
            font-weight: bold;
            color: #0369a1;
        }

        /* PhilHealth Enhancements */
        .philhealth-info {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #0369a1;
            text-align: justify;
        }

        .philhealth-info a {
            color: #0ea5e9;
            text-decoration: none;
            font-weight: 600;
        }

        .philhealth-info a:hover {
            text-decoration: underline;
        }

        /* Senior Citizen Enhancements */
        .senior-citizen-info {
            background: #fef3e7;
            border: 1px solid #f97316;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #c2410c;
            text-align: justify;
        }

        .senior-citizen-info i {
            margin-right: 6px;
        }

        .philhealth-categories {
            margin-top: 8px;
        }

        .philhealth-category {
            margin-bottom: 8px;
        }

        .philhealth-category-title {
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        .philhealth-subtypes {
            margin-left: 16px;
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.3;
        }

        /* Password Warning */
        .password-warning {
            display: none;
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-top: 4px;
            animation: fadeIn 0.3s ease;
        }

        .password-warning.show {
            display: block;
        }

        .password-warning i {
            margin-right: 6px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* PhilHealth Type Specific */
        .philhealth-fields {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Override: PhilHealth fields should be hidden by default like other conditional fields */
        .conditional-field.philhealth-fields {
            display: none;
            /* Hidden by default, same as other conditional fields */
        }

        .conditional-field.philhealth-fields.show {
            display: flex;
            /* Show as flex when activated */
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

        /* ------------------ Snackbar Notifications ------------------ */
        .snackbar-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            pointer-events: none;
        }

        .snackbar {
            background: #333;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease-in-out;
            pointer-events: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            word-wrap: break-word;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .snackbar.show {
            opacity: 1;
            transform: translateX(0);
        }

        .snackbar.error {
            background: #dc3545;
            border-left: 4px solid #721c24;
        }

        .snackbar.success {
            background: #28a745;
            border-left: 4px solid #155724;
        }

        .snackbar.warning {
            background: #ffc107;
            color: #212529;
            border-left: 4px solid #856404;
        }

        .snackbar.info {
            background: #17a2b8;
            border-left: 4px solid #0c5460;
        }

        .snackbar i {
            font-size: 16px;
            flex-shrink: 0;
        }

        .snackbar-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            margin-left: auto;
            padding: 0;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .snackbar-close:hover {
            opacity: 1;
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
        <!-- Snackbar container for notifications -->
        <div class="snackbar-container" id="snackbar-container"></div>

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

                <!-- Patient Information Section -->
                <div class="patient-info-section">
                    <h3 style="text-align: justify; font-weight: 600; margin: 0 0 20px 0;">Patient Information</h3>
                    <div class="grid">
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
                            <div style="position: relative;">
                                <input type="text" id="dob" name="dob" class="input-field" required
                                    placeholder="MM-DD-YYYY" maxlength="10"
                                    title="Enter date of birth in MM-DD-YYYY format (e.g., 01-25-1990)"
                                    autocomplete="bday" inputmode="numeric"
                                    value="<?php echo htmlspecialchars($formData['dob'] ?? '', ENT_QUOTES); ?>" />
                                <input type="date" id="dob-picker"
                                    min="1900-01-01" max="<?php echo date('Y-m-d'); ?>"
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; pointer-events: none;" />
                                <i class="fas fa-calendar-alt"
                                    style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); 
                                          cursor: pointer; color: #666; z-index: 10;"
                                    onclick="document.getElementById('dob-picker').showPicker ? document.getElementById('dob-picker').showPicker() : document.getElementById('dob-picker').click()"
                                    title="Open calendar picker"></i>
                            </div>
                            <small style="color: #666; font-size: 0.85em; margin-top: 4px; display: block;">
                                Enter date in MM-DD-YYYY format (e.g., 01-25-1990) or use calendar picker
                            </small>
                        </div>
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
                                    <div class="pwd-id-wrapper">
                                        <span class="pwd-prefix" id="pwd-prefix">12-6306-000-</span>
                                        <input type="text" id="pwd_id_number" name="pwd_id_number" class="input-field pwd-id-input"
                                            placeholder="0000000" maxlength="7" pattern="\d{7}"
                                            title="Enter exactly 7 digits for your PWD ID"
                                            value="<?php echo $formData['pwd_id_number']; ?>" />
                                    </div>
                                    <div class="pwd-instructions">
                                        <strong>Format:</strong> <span class="pwd-sample">12-6306-XXX-NNNNNNN</span><br>
                                        • The prefix (12-6306-XXX) is automatically generated based on your barangay<br>
                                        • You only need to enter the last 7 digits (NNNNNNN)<br>
                                        • <strong>Note:</strong> CHO Management will verify the validity of all PWD IDs
                                    </div>
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
                                            <option value="">Select Membership Type</option>
                                            <optgroup label="Direct Contributors">
                                                <option value="Employees" <?php echo $formData['philhealth_type'] === 'Employees' ? 'selected' : ''; ?>>Employees (with formal employment)</option>
                                                <option value="Kasambahay" <?php echo $formData['philhealth_type'] === 'Kasambahay' ? 'selected' : ''; ?>>Kasambahay</option>
                                                <option value="Self-earning" <?php echo $formData['philhealth_type'] === 'Self-earning' ? 'selected' : ''; ?>>Self-earning individuals; Professional practitioners</option>
                                                <option value="OFW" <?php echo $formData['philhealth_type'] === 'OFW' ? 'selected' : ''; ?>>Overseas Filipino Workers</option>
                                                <option value="Filipinos_abroad" <?php echo $formData['philhealth_type'] === 'Filipinos_abroad' ? 'selected' : ''; ?>>Filipinos living abroad and those with dual citizenship</option>
                                                <option value="Lifetime" <?php echo $formData['philhealth_type'] === 'Lifetime' ? 'selected' : ''; ?>>Lifetime members (21+ years, capacity to pay)</option>
                                            </optgroup>
                                            <optgroup label="Indirect Contributors">
                                                <option value="Indigents" <?php echo $formData['philhealth_type'] === 'Indigents' ? 'selected' : ''; ?>>Indigents (identified by DSWD)</option>
                                                <option value="4Ps" <?php echo $formData['philhealth_type'] === '4Ps' ? 'selected' : ''; ?>>Pantawid Pamilyang Pilipino Program beneficiaries</option>
                                                <option value="Senior_citizens" <?php echo $formData['philhealth_type'] === 'Senior_citizens' ? 'selected' : ''; ?>>Senior citizens</option>
                                                <option value="PWD" <?php echo $formData['philhealth_type'] === 'PWD' ? 'selected' : ''; ?>>Persons with disability</option>
                                                <option value="SK_officials" <?php echo $formData['philhealth_type'] === 'SK_officials' ? 'selected' : ''; ?>>Sangguniang Kabataan officials</option>
                                                <option value="LGU_sponsored" <?php echo $formData['philhealth_type'] === 'LGU_sponsored' ? 'selected' : ''; ?>>Point-of-service / LGU sponsored</option>
                                                <option value="No_capacity" <?php echo $formData['philhealth_type'] === 'No_capacity' ? 'selected' : ''; ?>>Filipinos 21+ years without capacity to pay</option>
                                                <option value="Solo_parent" <?php echo $formData['philhealth_type'] === 'Solo_parent' ? 'selected' : ''; ?>>Solo Parent</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="philhealth_id_number">PhilHealth ID Number</label>
                                        <input type="text" id="philhealth_id_number" name="philhealth_id_number" class="input-field"
                                            placeholder="Enter PhilHealth ID (12 digits)" maxlength="12"
                                            value="<?php echo $formData['philhealth_id_number']; ?>" />
                                    </div>
                                    <div class="philhealth-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Need help?</strong> Visit <a href="https://www.philhealth.gov.ph/members/" target="_blank" rel="noopener">philhealth.gov.ph/members/</a> for detailed membership guidance.<br>
                                        <strong>Note:</strong> CHO Management will verify all PhilHealth memberships.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Senior Citizen Section (auto-shown if 60+ years old) -->
                        <div class="checkbox-item" id="senior-citizen-section">
                            <input type="checkbox" id="isSenior" name="isSenior" value="1" <?php echo $formData['isSenior'] ? 'checked' : ''; ?> />
                            <div class="checkbox-content">
                                <label for="isSenior" class="checkbox-label">Senior Citizen</label>
                                <p class="checkbox-description">You are eligible for Senior Citizen benefits (60+ years old)</p>
                                <div class="conditional-field" id="senior-field">
                                    <label for="senior_citizen_id">Senior Citizen ID</label>
                                    <input type="text" id="senior_citizen_id" name="senior_citizen_id" class="input-field"
                                        placeholder="Enter Senior Citizen ID" value="<?php echo $formData['senior_citizen_id']; ?>" />
                                    <div class="senior-citizen-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Don't have your Senior Citizen ID yet?</strong> You can obtain it directly from your respective Barangay's Senior Citizens Office. They will assist you with the registration process and provide your official Senior Citizen ID.<br>
                                        <strong>Note:</strong> CHO Management will verify all Senior Citizen IDs for authenticity.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact Section (auto-shown if under 18 years old) -->
                        <div class="emergency-contact-section" id="emergency-contact-section">
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
                    <h3 style="text-align: justify; font-weight: 600; margin: 0 0 20px 0;">Contact Information</h3>
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
                        <div class="password-warning" id="password-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No special characters allowed in password.</strong> Using special characters will cause login failures.
                        </div>
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
            let birth;

            // Check if birthDate is in MMDDYYYY format (8 digits)
            if (/^\d{8}$/.test(birthDate)) {
                const mm = parseInt(birthDate.substring(0, 2), 10);
                const dd = parseInt(birthDate.substring(2, 4), 10);
                const yyyy = parseInt(birthDate.substring(4, 8), 10);
                birth = new Date(yyyy, mm - 1, dd); // month is 0-indexed
            } else if (/^\d{4}-\d{2}-\d{2}$/.test(birthDate)) {
                // Handle YYYY-MM-DD format (from database)
                birth = new Date(birthDate);
            } else if (/^\d{2}-\d{2}-\d{4}$/.test(birthDate)) {
                // Handle MM-DD-YYYY format (legacy)
                const [month, day, year] = birthDate.split('-').map(Number);
                birth = new Date(year, month - 1, day);
            } else {
                // Try to parse as-is
                birth = new Date(birthDate);
            }

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
                seniorSection.classList.remove('show');
                emergencySection.classList.remove('show');
                return;
            }

            const age = calculateAge(dobValue);

            // Show Senior Citizen section if 60 or older
            if (age >= 60) {
                seniorSection.classList.add('show');
                emergencySection.classList.remove('show');
            }
            // Show Emergency Contact section if under 18
            else if (age < 18) {
                emergencySection.classList.add('show');
                seniorSection.classList.remove('show');
                // Make emergency contact fields required
                $('#emergency_first_name').required = true;
                $('#emergency_last_name').required = true;
                $('#emergency_relationship').required = true;
                $('#emergency_contact_number').required = true;
            }
            // Hide both sections if age is between 18-59
            else {
                seniorSection.classList.remove('show');
                emergencySection.classList.remove('show');
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

        // --- Accessibility: make error region focusable so error.focus() works ---
        const error = $('#error');
        if (error && !error.hasAttribute('tabindex')) {
            error.setAttribute('tabindex', '-1');
        }

        // --- Barangay whitelist & UX improvement ---
        const validBarangays = new Set([
            <?php
            $jsBarangays = array_map(function ($brgy) {
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

            // Update PWD prefix based on selected barangay
            updatePwdPrefix();
        });

        // --- PWD ID Enhancement ---
        const barangayCodes = {
            'Brgy. Assumption': '001',
            'Brgy. Avanceña': '002',
            'Brgy. Cacub': '003',
            'Brgy. Caloocan': '004',
            'Brgy. Carpenter Hill': '005',
            'Brgy. Concepcion': '006',
            'Brgy. Esperanza': '007',
            'Brgy. General Paulino Santos': '008',
            'Brgy. Mabini': '009',
            'Brgy. Magsaysay': '010',
            'Brgy. Mambucal': '011',
            'Brgy. Morales': '012',
            'Brgy. Namnama': '013',
            'Brgy. New Pangasinan': '014',
            'Brgy. Paraiso': '015',
            'Brgy. Rotonda': '016',
            'Brgy. San Isidro': '017',
            'Brgy. San Roque': '018',
            'Brgy. San Jose': '019',
            'Brgy. Sta. Cruz': '020',
            'Brgy. Sto. Niño': '021',
            'Brgy. Saravia': '022',
            'Brgy. Topland': '023',
            'Brgy. Zone 1': '024',
            'Brgy. Zone 2': '025',
            'Brgy. Zone 3': '026',
            'Brgy. Zone 4': '027'
        };

        function updatePwdPrefix() {
            const selectedBarangay = barangaySelect.value;
            const code = barangayCodes[selectedBarangay] || '000';
            const pwdPrefix = $('#pwd-prefix');
            if (pwdPrefix) {
                pwdPrefix.textContent = `12-6306-${code}-`;
            }
        }

        // PWD ID input validation - only allow 7 digits
        const pwdIdInput = $('#pwd_id_number');
        if (pwdIdInput) {
            pwdIdInput.addEventListener('input', function() {
                // Remove non-digits and limit to 7 characters
                let value = this.value.replace(/\D/g, '');
                if (value.length > 7) {
                    value = value.slice(0, 7);
                }
                this.value = value;
            });

            pwdIdInput.addEventListener('paste', function(e) {
                e.preventDefault();
                let paste = (e.clipboardData || window.clipboardData).getData('text');
                let cleanPaste = paste.replace(/\D/g, '').substring(0, 7);
                this.value = cleanPaste;
            });
        }

        // Initialize PWD prefix on page load
        updatePwdPrefix();

        // --- Password Special Character Warning ---
        const passwordInput = $('#password');
        const confirmPasswordInput = $('#confirm-password');
        const passwordWarning = $('#password-warning');

        function checkSpecialCharacters(inputElement) {
            const value = inputElement.value;
            const hasSpecialChars = /[^a-zA-Z0-9]/.test(value);

            if (hasSpecialChars && passwordWarning) {
                passwordWarning.classList.add('show');
            } else if (passwordWarning) {
                passwordWarning.classList.remove('show');
            }
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                checkSpecialCharacters(this);
            });
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                checkSpecialCharacters(this);
            });
        }

        // --- Snackbar Notification System ---
        function showSnackbar(message, type = 'info', duration = 5000) {
            const container = document.getElementById('snackbar-container');
            if (!container) return;

            const snackbar = document.createElement('div');
            snackbar.className = `snackbar ${type}`;

            // Add appropriate icon based on type
            let icon = '';
            switch (type) {
                case 'error':
                    icon = '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'success':
                    icon = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'warning':
                    icon = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'info':
                default:
                    icon = '<i class="fas fa-info-circle"></i>';
                    break;
            }

            snackbar.innerHTML = `
                ${icon}
                <span>${message}</span>
                <button class="snackbar-close" onclick="this.parentElement.remove()">&times;</button>
            `;

            container.appendChild(snackbar);

            // Trigger animation
            setTimeout(() => snackbar.classList.add('show'), 10);

            // Auto remove after duration
            setTimeout(() => {
                snackbar.classList.remove('show');
                setTimeout(() => snackbar.remove(), 300);
            }, duration);

            return snackbar;
        }

        // --- Enhanced DOB MMDDYYYY Input ---
        const dobInput = $('#dob'); // Enhanced text input for MMDDYYYY

        // MMDDYYYY Date validation function
        function isValidDateMMDDYYYY(dateString) {
            // Check MMDDYYYY format (exactly 8 digits)
            if (!/^\d{8}$/.test(dateString)) {
                return {
                    valid: false,
                    error: 'Date must be exactly 8 digits in MMDDYYYY format'
                };
            }

            const mm = parseInt(dateString.substring(0, 2), 10);
            const dd = parseInt(dateString.substring(2, 4), 10);
            const yyyy = parseInt(dateString.substring(4, 8), 10);

            // Validate month (01-12)
            if (mm < 1 || mm > 12) {
                return {
                    valid: false,
                    error: 'Month must be between 01 and 12'
                };
            }

            // Validate year (reasonable range)
            const currentYear = new Date().getFullYear();
            if (yyyy < 1900 || yyyy > currentYear) {
                return {
                    valid: false,
                    error: `Year must be between 1900 and ${currentYear}`
                };
            }

            // Create date and validate day
            const date = new Date(yyyy, mm - 1, dd); // month is 0-indexed in Date constructor

            // Check if date is valid (handles leap years, different month lengths)
            if (date.getFullYear() !== yyyy || date.getMonth() !== (mm - 1) || date.getDate() !== dd) {
                return {
                    valid: false,
                    error: 'Invalid date for the given month and year'
                };
            }

            // Check if date is not in the future
            const today = new Date();
            today.setHours(23, 59, 59, 999); // End of today
            if (date > today) {
                return {
                    valid: false,
                    error: 'Date of birth cannot be in the future'
                };
            }

            // Check if date is not too far in the past (120 years)
            const minDate = new Date(currentYear - 120, 0, 1);
            if (date < minDate) {
                return {
                    valid: false,
                    error: 'Date of birth cannot be more than 120 years ago'
                };
            }

            return {
                valid: true,
                date: date
            };
        }

        // Convert MMDDYYYY to YYYY-MM-DD for database storage
        function convertMMDDYYYYToDbFormat(mmddyyyy) {
            if (!/^\d{8}$/.test(mmddyyyy)) {
                return mmddyyyy; // Return as-is if not in expected format
            }

            const mm = mmddyyyy.substring(0, 2);
            const dd = mmddyyyy.substring(2, 4);
            const yyyy = mmddyyyy.substring(4, 8);

            return `${yyyy}-${mm}-${dd}`;
        }

        // Convert YYYY-MM-DD to MMDDYYYY for display
        function convertDbFormatToMMDDYYYY(yyyymmdd) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(yyyymmdd)) {
                return yyyymmdd; // Return as-is if not in expected format
            }

            const [year, month, day] = yyyymmdd.split('-');
            return `${month}${day}${year}`;
        }

        // Validate and provide visual feedback with snackbar
        function validateDobInputMMDDYYYY() {
            const value = dobInput.value.trim();

            if (value === '') {
                dobInput.classList.remove('valid', 'invalid');
                return true; // Empty is okay initially
            }

            const validation = isValidDateMMDDYYYY(value);

            if (validation.valid) {
                dobInput.classList.remove('invalid');
                dobInput.classList.add('valid');
                checkAgeAndShowSections(); // Update age-based sections
                return true;
            } else {
                dobInput.classList.remove('valid');
                dobInput.classList.add('invalid');

                // Show snackbar with specific error message
                if (value.length === 8) { // Only show detailed errors for complete input
                    showSnackbar(validation.error, 'error', 4000);
                }

                return false;
            }
        }

        // DOB input handlers for MMDDYYYY format with MM-DD-YYYY display
        dobInput.addEventListener('input', function() {
            // Allow only numeric input, limit to 8 characters
            let value = this.value.replace(/\D/g, ''); // Remove non-digits

            if (value.length > 8) {
                value = value.substring(0, 8);
            }

            // Format as MM-DD-YYYY for display while typing
            let formattedValue = value;
            if (value.length >= 3) {
                formattedValue = value.substring(0, 2) + '-' + value.substring(2);
            }
            if (value.length >= 5) {
                formattedValue = value.substring(0, 2) + '-' + value.substring(2, 4) + '-' + value.substring(4);
            }

            // Store the cursor position
            const cursorPosition = this.selectionStart;
            const oldLength = this.value.length;

            this.value = formattedValue;

            // Adjust cursor position after formatting
            const newLength = this.value.length;
            const lengthDiff = newLength - oldLength;
            this.setSelectionRange(cursorPosition + lengthDiff, cursorPosition + lengthDiff);

            // Update date picker if we have a valid complete date (8 digits without hyphens)
            const cleanValue = value.replace(/\D/g, ''); // Remove hyphens for validation
            if (cleanValue.length === 8 && isValidDateMMDDYYYY(cleanValue).valid) {
                const dbFormat = convertMMDDYYYYToDbFormat(cleanValue);
                const dobPicker = $('#dob-picker');
                if (dobPicker) {
                    dobPicker.value = dbFormat;
                }
            }

            // Validate if we have a complete date (8 digits)
            if (cleanValue.length === 8) {
                // Temporarily store the clean value for validation
                const originalValue = this.value;
                this.value = cleanValue;
                const isValid = validateDobInputMMDDYYYY();
                this.value = originalValue; // Restore formatted value

                if (isValid) {
                    this.classList.remove('invalid');
                    this.classList.add('valid');
                    checkAgeAndShowSections();
                }
            } else if (cleanValue.length > 0 && cleanValue.length < 8) {
                // Remove validation classes for incomplete input
                this.classList.remove('valid', 'invalid');
            } else {
                // Empty input
                this.classList.remove('valid', 'invalid');
            }
        });

        dobInput.addEventListener('blur', function() {
            const value = this.value.trim();
            const cleanValue = value.replace(/\D/g, ''); // Remove hyphens for validation

            if (value === '') return; // Don't validate empty on blur

            if (cleanValue.length < 8) {
                showSnackbar('Date of birth must be exactly 8 digits (MM-DD-YYYY)', 'warning', 3000);
                this.classList.add('invalid');
                return;
            }

            // Temporarily store the clean value for validation
            const originalValue = this.value;
            this.value = cleanValue;
            const isValid = validateDobInputMMDDYYYY();
            this.value = originalValue; // Restore formatted value
        });

        dobInput.addEventListener('paste', function(e) {
            // Allow paste but filter to numbers only
            e.preventDefault();
            let paste = (e.clipboardData || window.clipboardData).getData('text');
            let cleanPaste = paste.replace(/\D/g, '').substring(0, 8);

            // Format the pasted value with hyphens
            let formattedPaste = cleanPaste;
            if (cleanPaste.length >= 3) {
                formattedPaste = cleanPaste.substring(0, 2) + '-' + cleanPaste.substring(2);
            }
            if (cleanPaste.length >= 5) {
                formattedPaste = cleanPaste.substring(0, 2) + '-' + cleanPaste.substring(2, 4) + '-' + cleanPaste.substring(4);
            }

            this.value = formattedPaste;

            // Trigger input event to validate
            this.dispatchEvent(new Event('input'));
        });

        // Initialize validation and format existing date
        if (dobInput.value) {
            // If the value is in YYYY-MM-DD format (from server), convert to MM-DD-YYYY for display
            if (/^\d{4}-\d{2}-\d{2}$/.test(dobInput.value)) {
                const convertedValue = convertDbFormatToMMDDYYYY(dobInput.value);
                // Format with hyphens for display
                let formattedValue = convertedValue;
                if (convertedValue.length >= 3) {
                    formattedValue = convertedValue.substring(0, 2) + '-' + convertedValue.substring(2);
                }
                if (convertedValue.length >= 5) {
                    formattedValue = convertedValue.substring(0, 2) + '-' + convertedValue.substring(2, 4) + '-' + convertedValue.substring(4);
                }
                dobInput.value = formattedValue;
            }
            // If already in MMDDYYYY format, add hyphens
            else if (/^\d{8}$/.test(dobInput.value)) {
                const value = dobInput.value;
                dobInput.value = value.substring(0, 2) + '-' + value.substring(2, 4) + '-' + value.substring(4);
            }

            // Validate the current value (using clean digits)
            const cleanValue = dobInput.value.replace(/\D/g, '');
            const originalValue = dobInput.value;
            dobInput.value = cleanValue;
            validateDobInputMMDDYYYY();
            dobInput.value = originalValue; // Restore formatted value
        }

        // Date picker functionality
        const dobPicker = $('#dob-picker');

        // Date picker handlers
        if (dobPicker) {
            dobPicker.addEventListener('change', function() {
                if (this.value) {
                    // Convert YYYY-MM-DD from picker to MM-DD-YYYY for display
                    const [year, month, day] = this.value.split('-');
                    const formattedValue = `${month}-${day}-${year}`;
                    dobInput.value = formattedValue;

                    // Validate using clean value
                    const cleanValue = `${month}${day}${year}`;
                    const originalValue = dobInput.value;
                    dobInput.value = cleanValue;
                    validateDobInputMMDDYYYY();
                    dobInput.value = originalValue; // Restore formatted value
                }
            });
        }

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
                showSnackbar('You must agree to the Terms & Conditions', 'error');
                return false;
            }

            // Barangay valid
            const brgy = $('#barangay').value;
            if (!validBarangays.has(brgy)) {
                e.preventDefault();
                showSnackbar('Please select a valid barangay', 'error');
                return false;
            }

            // Validate DOB in MMDDYYYY format
            const dobValue = $('#dob').value.trim();
            if (!dobValue) {
                showSnackbar('Date of birth is required', 'error');
                return false;
            }

            // Remove hyphens for validation
            const cleanDobValue = dobValue.replace(/\D/g, '');
            const dobValidation = isValidDateMMDDYYYY(cleanDobValue);
            if (!dobValidation.valid) {
                showSnackbar(dobValidation.error, 'error');
                $('#dob').focus();
                return false;
            }


            // Contact_Num: ensure 10 digits and starts with 9 (PH mobile)
            const digits = $('#contact-number').value.replace(/\D/g, '');
            if (!/^[0-9]{10}$/.test(digits)) {
                e.preventDefault();
                showSnackbar('Contact number must be 10 digits (e.g., 912 345 6789)', 'error');
                return false;
            }
            if (!/^9\d{9}$/.test(digits)) {
                e.preventDefault();
                showSnackbar('Contact number must start with 9 (PH mobile numbers)', 'error');
                return false;
            }

            // Additional information validation
            // PWD validation
            if (pwdCheckbox.checked) {
                const pwdId = $('#pwd_id_number').value.trim();
                if (!pwdId) {
                    e.preventDefault();
                    return showError('PWD ID Number is required when PWD is checked.');
                }

                // Validate PWD ID format (exactly 7 digits)
                const pwdDigits = pwdId.replace(/\D/g, '');
                if (pwdDigits.length !== 7) {
                    e.preventDefault();
                    return showError('PWD ID must be exactly 7 digits (last part of PWD ID format).');
                }

                // Check if barangay is selected for PWD prefix generation
                const selectedBarangay = $('#barangay').value;
                if (!selectedBarangay) {
                    e.preventDefault();
                    return showError('Please select your barangay first before entering PWD ID.');
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
            const dobValueForAge = $('#dob').value;
            if (dobValueForAge) {
                const age = calculateAge(dobValueForAge);

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
                showSnackbar('Please enter a valid email address', 'error');
                return false;
            }

            // Password rules (match the visual checker)
            const p1 = pw.value;
            const p2 = confirmPw.value;

            // Check for special characters
            const hasSpecialChars = /[^a-zA-Z0-9]/.test(p1) || /[^a-zA-Z0-9]/.test(p2);
            if (hasSpecialChars) {
                e.preventDefault();
                showSnackbar('Passwords cannot contain special characters. Only letters and numbers are allowed', 'error');
                return false;
            }

            const ok = reqs.length(p1) && reqs.uppercase(p1) && reqs.lowercase(p1) && reqs.number(p1);
            if (!ok) {
                e.preventDefault();
                showSnackbar('Password must be at least 8 chars with uppercase, lowercase, and a number', 'error');
                return false;
            }
            if (p1 !== p2) {
                e.preventDefault();
                showSnackbar('Passwords do not match', 'error');
                return false;
            }

            // Normalize & trim a few fields before storing/submitting
            $('#last-name').value = capitalizeWords($('#last-name').value.trim());
            $('#first-name').value = capitalizeWords($('#first-name').value.trim());
            $('#middle-name').value = capitalizeWords($('#middle-name').value.trim());
            $('#suffix').value = $('#suffix').value.trim().toUpperCase(); // keep suffix uppercase
            // store contact as digits only (server should expect this)
            $('#contact-number').value = digits;

            // Convert DOB from MM-DD-YYYY to YYYY-MM-DD for backend
            const finalDobValue = $('#dob').value.trim();
            if (finalDobValue && /^\d{2}-\d{2}-\d{4}$/.test(finalDobValue)) {
                // Remove hyphens and convert MMDDYYYY to YYYY-MM-DD
                const cleanDob = finalDobValue.replace(/\D/g, '');
                $('#dob').value = convertMMDDYYYYToDbFormat(cleanDob);
            }

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

            // Generate complete PWD ID if PWD is selected
            if (pwdCheckbox.checked) {
                const selectedBarangay = $('#barangay').value;
                const pwdDigits = $('#pwd_id_number').value.trim();
                const barangayCode = barangayCodes[selectedBarangay] || '000';
                const completePwdId = `12-6306-${barangayCode}-${pwdDigits.padStart(7, '0')}`;

                // Update the PWD input with the complete ID for form submission
                $('#pwd_id_number').value = completePwdId;
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