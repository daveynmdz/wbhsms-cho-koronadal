<?php
// registration_otp.php
session_start();
require_once '../../config/db.php';


// Only handle AJAX POST requests for OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $enteredOtp = trim($_POST['otp']);

    // Validate session
    if (!isset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['registration'])) {
        echo json_encode(['success' => false, 'message' => 'No registration session found. Please register again.']);
        exit;
    }

    // Expiry check
    if (time() > $_SESSION['otp_expiry']) {
        session_unset();
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please restart registration.']);
        exit;
    }

    // OTP check
    if ($enteredOtp !== $_SESSION['otp']) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        exit;
    }

    // OTP valid → insert patient
    $regData = $_SESSION['registration'];
    $hashedPassword = password_hash($regData['password'], PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO patients 
                (last_name, first_name, middle_name, suffix, barangay, dob, sex, contact_num, email, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $regData['last_name'],
            $regData['first_name'],
            $regData['middle_name'],
            $regData['suffix'],
            $regData['barangay'],
            $regData['dob'],
            $regData['sex'],
            $regData['contact_num'],
            $regData['email'],
            $hashedPassword
        ]);

        $patientId = $pdo->lastInsertId();

        // Fetch generated username
        $stmt2 = $pdo->prepare("SELECT username FROM patients WHERE id = ?");
        $stmt2->execute([$patientId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);

        $username = $row ? $row['username'] : null;

        // Cleanup session
        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['registration']);

        sleep(5); // Delay redirect for 5 seconds
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful.',
            'redirect' => 'registration_success.php',
            'username' => $username
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database insertion failed. Please try again later.']);
        exit;
    }
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
                        'Accept': 'application/json'
                    },
                    body: 'otp=' + encodeURIComponent(otp)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'registration_success.php?username=' + encodeURIComponent(data.username);
                    } else {
                        showError(data.message || 'Invalid OTP.');
                    }
                })
                .catch(() => showError('Server error. Please try again.'));
        });

        // resend OTP with cooldown
        resendBtn.addEventListener('click', () => {
            fetch('resend_registration_otp.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    showSnack(data.message || 'OTP resent', !data.success);
                    if (data.success) startCooldown(30); // start 30s cooldown
                })
                .catch(() => showSnack('Failed to resend OTP', true));
        });

        function startCooldown(seconds) {
            let remaining = seconds;
            resendBtn.disabled = true;
            resendBtn.style.opacity = '0.6';
            resendBtn.textContent = `Resend OTP (${remaining}s)`;

            const interval = setInterval(() => {
                remaining--;
                resendBtn.textContent = `Resend OTP (${remaining}s)`;

                if (remaining <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                    resendBtn.style.opacity = '1';
                    resendBtn.textContent = 'Resend OTP';
                }
            }, 1000);
        }
    </script>

</body>

</html>