<?php
// registration_otp.php
session_start();

// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php'; // must set $pdo (PDO, ERRMODE_EXCEPTION recommended)

// Helper: respond JSON for AJAX, otherwise redirect with a flash-style message
function respond($isAjax, $ok, $payload = [])
{
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $ok], $payload));
        exit;
    } else {
        // For non-AJAX: put message in session and redirect back to OTP page or success page
        $_SESSION['flash'] = $payload['message'] ?? ($ok ? 'OK' : 'Error');
        if ($ok && !empty($payload['redirect'])) {
            header('Location: ' . $payload['redirect']);
        } else {
            header('Location: registration_otp.php'); // same page
        }
        exit;
    }
}


$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isPost) {
    // ---- Read & validate input
    $enteredOtp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    if ($enteredOtp === '' || !ctype_digit($enteredOtp) || strlen($enteredOtp) < 4) { // adjust length as needed (e.g., 6)
        respond($isAjax, false, ['message' => 'Please enter a valid numeric OTP.']);
    }

    // ---- Validate session state
    if (!isset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['registration'])) {
        respond($isAjax, false, ['message' => 'No registration session found. Please register again.']);
    }

    // ---- Check expiry
    if (time() > (int)$_SESSION['otp_expiry']) {
        // keep only what you need; safest to clear all OTP-related data
        unset($_SESSION['otp'], $_SESSION['otp_expiry']);
        respond($isAjax, false, ['message' => 'OTP has expired. Please restart registration.']);
    }

    // ---- Check OTP (compare as strings to avoid type quirks)
    if ($enteredOtp !== strval($_SESSION['otp'])) {
        respond($isAjax, false, ['message' => 'Invalid OTP. Please try again.']);
    }

    // ---- OTP valid -> insert patient
    $regData = $_SESSION['registration'];
    $hashedPassword = password_hash($regData['password'], PASSWORD_DEFAULT);

    try {
        // Email uniqueness check removed to allow multiple patients with the same email

        // Insert inside a transaction (safer if you add more steps later)
        $pdo->beginTransaction();

        // First, insert the patient record without username to get the patient_id
        $sql = "INSERT INTO patients
                (first_name, middle_name, last_name, suffix, barangay_id, date_of_birth, sex, contact_number, email, password_hash, isPWD, pwd_id_number, isPhilHealth, philhealth_type, philhealth_id_number, isSenior, senior_citizen_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $regData['first_name']  ?? null,
            $regData['middle_name'] ?? null,
            $regData['last_name']   ?? null,
            $regData['suffix']      ?? null,
            $regData['barangay_id'] ?? null,
            $regData['dob']         ?? null,
            $regData['sex']         ?? null,
            $regData['contact_num'] ?? null,
            $regData['email']       ?? null,
            $hashedPassword,
            $regData['isPWD']       ?? 0,
            $regData['pwd_id_number'] ?? null,
            $regData['isPhilHealth'] ?? 0,
            $regData['philhealth_type'] ?? null,
            $regData['philhealth_id_number'] ?? null,
            $regData['isSenior']    ?? 0,
            $regData['senior_citizen_id'] ?? null
        ]);

        $patientId = $pdo->lastInsertId();

        // Now generate and update the username based on the patient_id
        $generatedUsername = 'P' . str_pad($patientId, 6, '0', STR_PAD_LEFT);
        $updateSql = "UPDATE patients SET username = ? WHERE patient_id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateResult = $updateStmt->execute([$generatedUsername, $patientId]);

        if (!$updateResult) {
            throw new PDOException('Failed to set username for patient');
        }

        // Verify the username was set correctly
        $verifySql = "SELECT username FROM patients WHERE patient_id = ?";
        $verifyStmt = $pdo->prepare($verifySql);
        $verifyStmt->execute([$patientId]);
        $verifyResult = $verifyStmt->fetchColumn();

        if ($verifyResult !== $generatedUsername) {
            throw new PDOException('Username verification failed after update');
        }

        // Check if patient is a minor and has emergency contact data
        if (!empty($regData['emergency_first_name']) && !empty($regData['emergency_last_name'])) {
            $emergencyContactSql = "INSERT INTO emergency_contact 
                                   (patient_id, emergency_first_name, emergency_last_name, emergency_relationship, emergency_contact_number) 
                                   VALUES (?, ?, ?, ?, ?)";
            $emergencyStmt = $pdo->prepare($emergencyContactSql);
            $emergencyResult = $emergencyStmt->execute([
                $patientId,
                $regData['emergency_first_name'] ?? null,
                $regData['emergency_last_name'] ?? null,
                $regData['emergency_relationship'] ?? null,
                $regData['emergency_contact_number'] ?? null
            ]);

            if (!$emergencyResult) {
                throw new PDOException('Failed to insert emergency contact information');
            }
        }

        $pdo->commit();

        // Cleanup OTP + registration payload
        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['registration']);

        // Store username in session to show on success page
        $_SESSION['registration_username'] = $generatedUsername;

        respond($isAjax, true, [
            'message'  => 'Registration successful.',
            'redirect' => 'registration_success.php',
            'id'       => $patientId,
            'email'    => $regData['email'],
            'username' => $generatedUsername
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        respond($isAjax, false, ['message' => 'Database error: ' . $e->getMessage()]);
    }
    // exit after POST
    exit;
}

// For non-AJAX requests, just render the HTML below (no JSON output)
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify OTP • Registration</title>
    <link rel="stylesheet" href="../../assets/css/login.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        /* --- Reuse the same variables & styles --- */
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            touch-action: manipulation;
        }

        @media (max-width: 768px) {
            body {
                background-attachment: scroll;
            }
        }

        /* Touch-friendly tap targets for mobile */
        @media (max-width: 768px) {

            .btn,
            .resend-link {
                min-height: 44px;
                min-width: 44px;
            }
        }

        /* Prevent zoom on input focus for iOS */
        @media screen and (max-width: 768px) {

            input[type="text"],
            input[type="email"],
            input[type="tel"],
            input[type="password"] {
                font-size: 16px !important;
            }
        }

        header {
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            background: transparent;
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
        }

        @media screen and (max-width: 768px) {
            .logo-container {
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 14px 0;
            }

            .logo {
                width: 80px;
                height: auto;
            }
        }

        .otp-section {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 140px 20px 40px;
            position: relative;
        }

        @media (max-width: 768px) {
            .otp-section {
                padding: 150px 16px 32px;
                align-items: flex-start;
                padding-top: 140px;
            }
        }

        @media (max-width: 480px) {
            .otp-section {
                padding: 100px 12px 24px;
                padding-top: 120px;
            }
        }

        @media (max-height: 700px) and (max-width: 480px) {
            .otp-section {
                padding: 150px 12px 20px;
                align-items: flex-start;
            }
        }

        .otp-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.1) 0%, rgba(0, 86, 179, 0.05) 100%);
            pointer-events: none;
        }

        .otp-box {
            width: 100%;
            min-width: 320px;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15), 0 8px 20px rgba(0, 123, 255, 0.1);
            text-align: center;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
            margin: 0 16px;
        }

        @media (max-width: 768px) {
            .otp-box {
                min-width: 280px;
                max-width: calc(100vw - 32px);
                padding: 32px 24px;
                border-radius: 20px;
                margin: 0 16px;
            }
        }

        @media (max-width: 480px) {
            .otp-box {
                min-width: unset;
                max-width: calc(100vw - 24px);
                padding: 24px 20px;
                border-radius: 16px;
                margin: 0 12px;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .otp-title {
            margin: 0 0 12px 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text);
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-600) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        @media (max-width: 768px) {
            .otp-title {
                font-size: 1.5rem;
                margin: 0 0 16px 0;
            }
        }

        @media (max-width: 480px) {
            .otp-title {
                font-size: 1.35rem;
                margin: 0 0 12px 0;
            }
        }

        .otp-instructions {
            color: #64748b;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 32px;
            font-weight: 400;
        }

        @media (max-width: 768px) {
            .otp-instructions {
                font-size: 0.95rem;
                margin-bottom: 28px;
                line-height: 1.5;
            }
        }

        @media (max-width: 480px) {
            .otp-instructions {
                font-size: 0.9rem;
                margin-bottom: 24px;
                padding: 0 8px;
            }
        }

        .otp-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .otp-input {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            letter-spacing: 0.8em;
            font-size: 1.8rem;
            font-weight: 600;
            text-align: center;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            color: var(--text);
            transition: all 0.3s ease;
            outline: none;
        }

        @media (max-width: 768px) {
            .otp-input {
                max-width: 280px;
                font-size: 1.6rem;
                padding: 14px 18px;
                letter-spacing: 0.6em;
            }
        }

        @media (max-width: 480px) {
            .otp-input {
                font-size: 1.4rem;
                padding: 12px 16px;
                letter-spacing: 0.5em;
                border-radius: 12px;
            }
        }

        @media (max-width: 360px) {
            .otp-input {
                font-size: 1.2rem;
                padding: 10px 14px;
                letter-spacing: 0.4em;
            }
        }

        .otp-input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.15);
            transform: translateY(-2px);
        }

        .otp-input:valid {
            border-color: #22c55e;
            background: #f0fdf4;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-600) 100%);
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            min-height: 48px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn.secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn.secondary:hover {
            background: #e2e8f0;
            color: #334155;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-group {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .btn-group .btn {
            flex: 1;
            min-width: 120px;
            max-width: 200px;
        }

        @media (max-width: 768px) {
            .btn-group {
                gap: 10px;
                margin-top: 16px;
            }

            .btn-group .btn {
                min-width: 110px;
                max-width: 160px;
                font-size: 0.9rem;
                padding: 12px 18px;
            }
        }

        @media (max-width: 580px) {
            .btn-group {
                flex-direction: column;
                gap: 12px;
                max-width: 280px;
                margin: 16px auto 0;
            }

            .btn-group .btn {
                max-width: none;
                min-width: unset;
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .btn-group {
                max-width: 100%;
                gap: 10px;
            }

            .btn-group .btn {
                font-size: 0.85rem;
                padding: 11px 16px;
                min-height: 44px;
            }
        }

        .resend-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        @media (max-width: 768px) {
            .resend-section {
                margin-top: 20px;
                padding-top: 20px;
            }
        }

        @media (max-width: 480px) {
            .resend-section {
                margin-top: 16px;
                padding-top: 16px;
            }
        }

        .resend-text {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .resend-text {
                font-size: 0.85rem;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 480px) {
            .resend-text {
                font-size: 0.8rem;
                margin-bottom: 8px;
                padding: 0 12px;
            }
        }

        .resend-link {
            background: none;
            border: none;
            color: var(--brand);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 768px) {
            .resend-link {
                font-size: 0.9rem;
                padding: 10px 18px;
            }
        }

        @media (max-width: 480px) {
            .resend-link {
                font-size: 0.85rem;
                padding: 8px 16px;
                gap: 4px;
            }
        }

        .resend-link:hover {
            background: rgba(0, 123, 255, 0.1);
            color: var(--brand-600);
        }

        .resend-link:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .resend-link:disabled:hover {
            background: none;
            color: var(--brand);
        }

        .error {
            display: none;
            margin: 16px 0;
            padding: 16px 20px;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #f87171;
            color: #dc2626;
            border-radius: 12px;
            font-weight: 500;
            text-align: left;
            position: relative;
            animation: shakeError 0.5s ease-out;
        }

        @media (max-width: 768px) {
            .error {
                margin: 12px 0;
                padding: 14px 16px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .error {
                margin: 10px 0;
                padding: 12px 14px;
                font-size: 0.85rem;
                border-radius: 10px;
            }
        }

        @keyframes shakeError {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .error::before {
            content: '⚠️';
            margin-right: 8px;
        }

        .dev-message {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 16px 0;
            font-weight: 600;
            text-align: left;
            position: relative;
        }

        @media (max-width: 768px) {
            .dev-message {
                padding: 14px 16px;
                margin: 12px 0;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .dev-message {
                padding: 12px 14px;
                margin: 10px 0;
                font-size: 0.85rem;
                border-radius: 10px;
            }
        }

        .dev-message::before {
            content: '🔧';
            margin-right: 8px;
        }

        #snackbar {
            position: fixed;
            left: 50%;
            bottom: 32px;
            transform: translateX(-50%) translateY(20px);
            min-width: 320px;
            max-width: 90vw;
            padding: 16px 24px;
            border-radius: 16px;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
            z-index: 99999;
            font: 600 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 768px) {
            #snackbar {
                min-width: 280px;
                max-width: calc(100vw - 32px);
                padding: 14px 20px;
                bottom: 24px;
                font-size: 13px;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            #snackbar {
                min-width: unset;
                max-width: calc(100vw - 24px);
                padding: 12px 16px;
                bottom: 20px;
                font-size: 12px;
                gap: 8px;
                border-radius: 12px;
            }
        }

        #snackbar::before {
            content: '✓';
            font-size: 16px;
            font-weight: bold;
        }

        #snackbar.error {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }

        #snackbar.error::before {
            content: '⚠';
        }

        #snackbar.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
            pointer-events: auto;
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

    <section class="otp-section">
        <div class="otp-box">
            <h2 class="otp-title">Verify Your Registration</h2>
            <div class="otp-instructions">
                Please enter the 6-digit verification code sent to your email address to complete your registration.
            </div>

            <?php if (isset($_SESSION['dev_message']) && (getenv('APP_DEBUG') === '1')): ?>
                <div class="dev-message">
                    <?php echo htmlspecialchars($_SESSION['dev_message'], ENT_QUOTES, 'UTF-8');
                    unset($_SESSION['dev_message']); ?>
                </div>
            <?php endif; ?>

            <form class="otp-form" id="otpForm" autocomplete="one-time-code" novalidate>
                <input type="text" maxlength="6" class="otp-input" id="otp" name="otp" placeholder="000000" required
                    inputmode="numeric" pattern="\d{6}" />
                <div id="errorMsg" class="error" role="alert" aria-live="polite"></div>

                <div class="btn-group">
                    <button id="backBtn" type="button" class="btn secondary">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </button>
                    <button id="reviewBtn" type="button" class="btn secondary" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none;">
                        <i class="fas fa-edit"></i> Edit Registration
                    </button>
                    <button id="submitBtn" type="submit" class="btn">
                        <i class="fas fa-check"></i> Verify Code
                    </button>
                </div>
            </form>

            <div class="resend-section">
                <div class="resend-text">Didn't receive the verification code?</div>
                <button class="resend-link" id="resendBtn" type="button">
                    <i class="fas fa-redo"></i> Resend Code
                </button>
            </div>
        </div>
    </section>

    <div id="snackbar" role="status" aria-live="polite" aria-atomic="true"></div>

    <script>
        const form = document.getElementById('otpForm');
        const input = document.getElementById('otp');
        const errorMsg = document.getElementById('errorMsg');
        const submitBtn = document.getElementById('submitBtn');
        const backBtn = document.getElementById('backBtn');
        const reviewBtn = document.getElementById('reviewBtn');
        const snackbar = document.getElementById('snackbar');
        const resendBtn = document.getElementById('resendBtn');

        backBtn.addEventListener('click', () => {
            window.location.href = '../auth/patient_login.php';
        });
        reviewBtn.addEventListener('click', () => {
            window.location.href = 'patient_registration.php';
        });

        function showError(msg) {
            errorMsg.textContent = msg;
            errorMsg.style.display = 'block';
        }

        function clearError() {
            errorMsg.textContent = '';
            errorMsg.style.display = 'none';
        }

        function showSnack(msg, isError = false) {
            snackbar.textContent = msg;
            snackbar.classList.toggle('error', !!isError);
            snackbar.classList.remove('show');
            void snackbar.offsetWidth;
            snackbar.classList.add('show');
            setTimeout(() => snackbar.classList.remove('show'), 4000);
        }

        // auto format OTP input
        input.addEventListener('input', () => {
            const v = input.value.replace(/\D+/g, '').slice(0, 6);
            if (v !== input.value) input.value = v;
            if (v.length === 6) form.requestSubmit();
        });

        // submit OTP to backend
        form.addEventListener('submit', e => {
            e.preventDefault();
            clearError();

            const otp = input.value.trim();
            if (!/^\d{6}$/.test(otp)) {
                showError('Please enter the 6-digit code.');
                return;
            }

            fetch('registration_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest' // <-- add this

                    },
                    body: 'otp=' + encodeURIComponent(otp)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'registration_success.php?id=' + encodeURIComponent(data.id) + '&email=' + encodeURIComponent(data.email);
                    } else {
                        showError(data.message || 'Invalid OTP.');
                    }
                })
                .catch(() => showError('Server error. Please try again.'));
        });

        // resend OTP with cooldown
        let cooldownInterval = null;

        function startCooldown(seconds) {
            let remaining = seconds;
            resendBtn.disabled = true;
            resendBtn.style.opacity = '0.6';
            resendBtn.textContent = `Resend OTP (${remaining}s)`;

            if (cooldownInterval) clearInterval(cooldownInterval);
            cooldownInterval = setInterval(() => {
                remaining--;
                resendBtn.textContent = `Resend OTP (${remaining}s)`;
                if (remaining <= 0) {
                    clearInterval(cooldownInterval);
                    cooldownInterval = null;
                    resetResendBtn();
                }
            }, 1000);
        }

        function resetResendBtn() {
            resendBtn.disabled = false;
            resendBtn.style.opacity = '1';
            resendBtn.textContent = 'Resend OTP';
            if (cooldownInterval) {
                clearInterval(cooldownInterval);
                cooldownInterval = null;
            }
        }

        // Start cooldown on page load
        window.addEventListener('DOMContentLoaded', () => {
            startCooldown(30);
        });

        resendBtn.addEventListener('click', () => {
            if (resendBtn.disabled) return; // Prevent multiple clicks
            startCooldown(30); // Start cooldown immediately on click
            fetch('resend_registration_otp.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    showSnack(data.message || 'OTP resent', !data.success);
                    // If resend failed, reset button immediately
                    if (!data.success) {
                        resetResendBtn();
                    }
                })
                .catch(() => {
                    showSnack('Failed to resend OTP', true);
                    resetResendBtn();
                });
        });
    </script>

</body>

</html>