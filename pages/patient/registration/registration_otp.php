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

        .otp-section {
            min-height: 100vh;
            display: grid;
            place-items: start center;
            padding: 160px 16px 40px;
        }

        .otp-box {
            width: 100%;
            min-width: 350px;
            max-width: 600px;
            background: var(--surface);
            border-radius: 16px;
            padding: 24px 22px 28px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .otp-title {
            margin: 0 0 18px 0;
            font-size: 1.4rem;
            color: var(--text);
        }

        .otp-instructions {
            color: var(--muted);
            font-size: 1rem;
            margin-bottom: 18px;
        }

        .otp-input {
            letter-spacing: .4em;
            font-size: 1.5rem;
            text-align: center;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 10px;
            background-color: var(--brand);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.secondary {
            background-color: #e5e7eb;
            color: #111827;
        }

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

        #snackbar {
            position: fixed;
            left: 50%;
            bottom: 24px;
            transform: translateX(-50%) translateY(20px);
            min-width: 260px;
            max-width: 92vw;
            padding: 12px 16px;
            border-radius: 10px;
            background: #16a34a;
            color: #fff;
            opacity: 0;
            pointer-events: none;
            transition: transform .25s ease, opacity .25s ease;
            z-index: 99999;
            font: 600 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        #snackbar.error {
            background: #dc2626;
        }

        #snackbar.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
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
            <h2 class="otp-title">Verify OTP for Registration</h2>
            <div class="otp-instructions">
                Please enter the One-Time Password (OTP) sent to your email to complete your registration.
            </div>
            
            <?php if (isset($_SESSION['dev_message'])): ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; border-radius: 8px; margin: 10px 0; font-weight: bold;">
                    <?php echo htmlspecialchars($_SESSION['dev_message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['dev_message']); ?>
                </div>
            <?php endif; ?>

            <form class="otp-form" id="otpForm" autocomplete="one-time-code" novalidate>
                <input type="text" maxlength="6" class="otp-input" id="otp" name="otp" placeholder="Enter OTP" required
                    inputmode="numeric" pattern="\d{6}" />
                <div id="errorMsg" class="error" role="alert" aria-live="polite"></div>

                <div style="display:flex;justify-content:center;gap:12px;margin-top:36px;">
                    <button id="backBtn" type="button" class="btn secondary">Back to Login</button>
                    <button id="reviewBtn" type="button" class="btn" style="background:#ffc107; color:#333;">Go Back to Registration</button>
                    <button id="submitBtn" type="submit" class="btn">Verify OTP</button>
                </div>
            </form>

            <div style="text-align:center; margin-top:12px; font-size:0.85em; color:#555;">
                Didn’t receive the code?
                <button class="resend-link" id="resendBtn" type="button" style="background:none; border:none; color:#007bff; text-decoration:underline; font-size:1em; cursor:pointer; padding:0;">Resend OTP</button>
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
            window.location.href = 'patient_login.php';
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