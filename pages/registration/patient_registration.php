<?php // patient_registration.php 
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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
    'email' => ''
];
if (isset($_SESSION['registration']) && is_array($_SESSION['registration'])) {
    foreach ($formData as $k => $v) {
        if (isset($_SESSION['registration'][$k])) {
            $formData[$k] = htmlspecialchars($_SESSION['registration'][$k], ENT_QUOTES, 'UTF-8');
        }
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
                            $barangays = [
                                'Brgy. Assumption',
                                'Brgy. Avanceña',
                                'Brgy. Cacub',
                                'Brgy. Caloocan',
                                'Brgy. Carpenter Hill',
                                'Brgy. Concepcion',
                                'Brgy. Esperanza',
                                'Brgy. General Paulino Santos',
                                'Brgy. Mabini',
                                'Brgy. Magsaysay',
                                'Brgy. Mambucal',
                                'Brgy. Morales',
                                'Brgy. Namnama',
                                'Brgy. New Pangasinan',
                                'Brgy. Paraiso',
                                'Brgy. Rotonda',
                                'Brgy. San Isidro',
                                'Brgy. San Roque',
                                'Brgy. San Jose',
                                'Brgy. Sta. Cruz',
                                'Brgy. Sto. Niño',
                                'Brgy. Saravia',
                                'Brgy. Topland',
                                'Brgy. Zone 1',
                                'Brgy. Zone 2',
                                'Brgy. Zone 3',
                                'Brgy. Zone 4'
                            ];
                            foreach ($barangays as $b) {
                                $selected = ($formData['barangay'] === $b) ? 'selected' : '';
                                $label = htmlspecialchars($b, ENT_QUOTES, 'UTF-8');
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
                        <input type="date" id="dob" name="dob" class="input-field" required value="<?php echo $formData['dob']; ?>" />
                    </div>
                    <!-- Hidden field for MM-DD-YYYY DOB format -->
                    <input type="hidden" name="dob_mdy" id="dob_mdy" />

                    <div>
                        <label for="contact-number">Contact No.*</label>
                        <div class="contact-input-wrapper">
                            <span class="prefix">+63</span>
                            <input type="tel" id="contact-number" name="contact_num" class="input-field contact-number" placeholder="### ### ####" maxlength="13" inputmode="numeric" autocomplete="tel-national" required value="<?php echo $formData['contact_num']; ?>" />
                        </div>
                    </div>

                    <div class="span-2">
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
        const dob = $('#dob');
        const setDobBounds = () => {
            const today = new Date();
            const max = today.toISOString().split('T')[0];
            const min = new Date(today.getFullYear() - 120, today.getMonth(), today.getDate())
                .toISOString()
                .split('T')[0];
            dob.max = max;
            dob.min = min;
        };
        setDobBounds();

        // --- Accessibility: make error region focusable so error.focus() works ---
        const error = $('#error');
        if (error && !error.hasAttribute('tabindex')) {
            error.setAttribute('tabindex', '-1');
        }

        // --- Barangay whitelist & UX improvement ---
        const validBarangays = new Set([
            'Brgy. Assumption', 'Brgy. Avanceña', 'Brgy. Cacub', 'Brgy. Caloocan', 'Brgy. Carpenter Hill', 'Brgy. Concepcion', 'Brgy. Esperanza', 'Brgy. General Paulino Santos', 'Brgy. Mabini', 'Brgy. Magsaysay', 'Brgy. Mambucal', 'Brgy. Morales', 'Brgy. Namnama', 'Brgy. New Pangasinan', 'Brgy. Paraiso', 'Brgy. Rotonda', 'Brgy. San Isidro', 'Brgy. San Roque', 'Brgy. San Jose', 'Brgy. Sta. Cruz', 'Brgy. Sto. Niño', 'Brgy. Saravia', 'Brgy. Topland', 'Brgy. Zone 1', 'Brgy. Zone 2', 'Brgy. Zone 3', 'Brgy. Zone 4'
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

            // Convert DOB to MM-DD-YYYY for PHP
            const dobInput = document.getElementById('dob');
            const dobHidden = document.getElementById('dob_mdy');
            if (dobInput && dobHidden && dobInput.value) {
                const [yyyy, mm, dd] = dobInput.value.split('-');
                if (yyyy && mm && dd) {
                    dobHidden.value = `${mm}-${dd}-${yyyy}`;
                } else {
                    dobHidden.value = '';
                }
            }

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

            // DOB guard
            if (dobInput.value) {
                const d = new Date(dobInput.value);
                const today = new Date();
                const oldest = new Date(today.getFullYear() - 120, today.getMonth(), today.getDate());
                if (d > today || d < oldest) {
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
                email: $('#email').value
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