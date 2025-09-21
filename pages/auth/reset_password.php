<?php
// reset_password.php
declare(strict_types=1);

session_start(); // Ensure session is started at the very top

ini_set('display_errors', '1'); // turn off in production
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// --- Guard: require verified OTP context on GET ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['otp_verified_for_reset']) || empty($_SESSION['reset_user_id'])) {
        header('Location: forgot_password.php', true, 303);
        exit;
    }
}

// --- Handle POST: change password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check for required session data
    if (empty($_SESSION['otp_verified_for_reset']) || empty($_SESSION['reset_user_id'])) {
        // Set flash message for session expiration or unauthorized access
        $_SESSION['flash'] = ['type' => 'fail', 'msg' => 'Unauthorized or session expired.'];
        echo json_encode(['success' => false, 'message' => 'Unauthorized or session expired.']);
        exit;
    }

    $userId   = (int)$_SESSION['reset_user_id'];
    $password = (string)($_POST['password'] ?? '');

    // Check if password is empty
    if ($password === '') {
        $_SESSION['flash'] = ['type' => 'fail', 'msg' => 'Password is required.'];
        echo json_encode(['success' => false, 'message' => 'Password is required.']);
        exit;
    }

    // Strength check (mirror frontend)
    $isStrong =
        strlen($password) >= 8 &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/[a-z]/', $password) &&
        preg_match('/[0-9]/', $password);

    if (!$isStrong) {
        $_SESSION['flash'] = ['type' => 'fail', 'msg' => 'Password must be at least 8 characters and include uppercase, lowercase, and a number.'];
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase, and a number.']);
        exit;
    }

    // Hash the password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Update patients.password in the database (adjust column/table if needed)
        $stmt = $pdo->prepare('UPDATE patients SET password_hash = ? WHERE patient_id = ?');
        $stmt->execute([$hash, $userId]);

        // If the password is updated, clean up session and set flash message
        unset($_SESSION['otp_verified_for_reset'], $_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_name']);

        // Set flash message for success
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password changed successfully. Please log in.'];

        // Return success response to AJAX
        echo json_encode(['success' => true, 'message' => 'Password changed successfully. Please log in.']);
        exit;
    } catch (Throwable $e) {
        // Log error if DB query fails
        error_log('[reset_password] DB Error: ' . $e->getMessage());

        // Set flash message for failure
        $_SESSION['flash'] = ['type' => 'fail', 'msg' => 'Database error. Please try again.'];

        // Return failure response to AJAX
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Change Password</title>
    <link rel="stylesheet" href="../../assets/css/login.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        :root {
            --brand: #007bff;
            --brand-600: #0056b3;
            --text: #03045e;
            --muted: #6c757d;
            --border: #ced4da;
            --surface: #ffffff;
            --shadow: 0 8px 20px rgba(0, 0, 0, .15);
            --focus-ring: 0 0 0 3px rgba(0, 123, 255, .25);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 14px 0
        }

        .logo {
            width: 100px;
            height: auto
        }

        .homepage {
            min-height: 100vh;
            display: grid;
            place-items: start center;
            padding: 160px 16px 40px
        }

        .form-box {
            width: 100%;
            min-width: 350px;
            max-width: 600px;
            background: var(--surface);
            border-radius: 16px;
            padding: 20px 22px 26px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative
        }

        label {
            display: block;
            text-align: left;
            font-weight: 600;
            margin: 6px 0 6px;
            color: #333
        }

        .input-field,
        select {
            height: 44px;
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
            margin-bottom: 0;
        }

        .input-field::placeholder {
            color: #8a8f98
        }

        .input-field:focus,
        select:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: var(--focus-ring)
        }

        .password-wrapper {
            position: relative;
            display: grid
        }

        .password-wrapper .input-field {
            padding-right: 42px
        }

        .toggle-password {
            position: absolute;
            top: 72%;
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
            box-shadow: var(--focus-ring)
        }

        .password-requirements {
            padding: 12px 14px;
            list-style: none;
            font-size: .95em;
            color: #555;
            margin: 10px 0 20px;
            background: #f8fafc;
            border: 1px dashed var(--border);
            border-radius: 10px;
            text-align: left
        }

        .password-requirements h4 {
            margin: 0 0 10px;
            font-size: 1rem
        }

        .password-requirements li {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .icon.green {
            color: green
        }

        .icon.red {
            color: red
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
            transition: transform .12s, box-shadow .12s, background-color .12s;
        }

        .btn:hover {
            box-shadow: 0 6px 16px rgba(0, 123, 255, .25);
            background-color: var(--brand-600);
            transform: translateY(-1px)
        }

        .btn:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring)
        }

        .error {
            display: none;
            margin: .6rem 0;
            padding: .65rem .75rem;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            margin-bottom: 18px
        }

        /* Small button spinner */
        .spinner {
            display: inline-block;
            width: 1em;
            height: 1em;
            margin-right: 8px;
            border-radius: 50%;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-right-color: currentColor;
            animation: spin .7s linear infinite;
            vertical-align: -2px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="logo-container">
            <a href="../../index.php" tabindex="0">
                <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128" alt="CHO Koronadal Logo" />
            </a>
        </div>
    </header>

    <section class="homepage">
        <div class="form-box">
            <h2>Change Password</h2>

            <form id="changePwForm" class="form" autocomplete="off" novalidate>
                <div class="password-wrapper">
                    <label for="password">New Password*</label>
                    <input type="password" id="password" name="password" class="input-field" required autocomplete="new-password" aria-describedby="pw-req" />
                    <i class="fa-solid fa-eye toggle-password" aria-hidden="true"></i>
                </div>

                <div class="password-wrapper">
                    <label for="confirm-password">Confirm New Password*</label>
                    <input type="password" id="confirm-password" name="confirm_password" class="input-field" required autocomplete="new-password" />
                    <i class="fa-solid fa-eye toggle-password" aria-hidden="true"></i>
                </div>

                <ul class="password-requirements" id="password-requirements">
                    <h4 id="pw-req">Password Requirements:</h4>
                    <li id="length"><i class="fa-solid fa-circle-xmark icon red"></i> At least 8 characters</li>
                    <li id="uppercase"><i class="fa-solid fa-circle-xmark icon red"></i> At least one uppercase letter</li>
                    <li id="lowercase"><i class="fa-solid fa-circle-xmark icon red"></i> At least one lowercase letter</li>
                    <li id="number"><i class="fa-solid fa-circle-xmark icon red"></i> At least one number</li>
                    <li id="match"><i class="fa-solid fa-circle-xmark icon red"></i> Passwords match</li>
                </ul>

                <div id="error" class="error" role="alert" aria-live="polite"></div>

                <div class="form-footer" style="text-align:center; margin-top:18px;">
                    <button id="backBtn" type="button" class="btn" style="background:#eee; color:#333; margin-right:8px;">
                        Back to Login
                    </button>
                    <button id="submitBtn" type="submit" class="btn">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        const $ = (sel, ctx = document) => ctx.querySelector(sel);
        const pw = $('#password');
        const confirmPw = $('#confirm-password');
        const form = document.getElementById('changePwForm');
        const error = document.getElementById('error');
        const submitBtn = document.getElementById('submitBtn');
        const backBtn = document.getElementById('backBtn');

        backBtn.addEventListener('click', () => {
            window.location.href = 'forgot_password_otp.php';
        });

        // Password visibility toggle
        document.addEventListener('click', (e) => {
            const icon = e.target.closest('.toggle-password');
            if (!icon) return;
            const input = icon.previousElementSibling;
            if (!input) return;
            const isPw = input.type === 'password';
            input.type = isPw ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isPw);
            icon.classList.toggle('fa-eye-slash', isPw);
        });

        // Live requirements
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

        function showError(msg) {
            error.textContent = msg;
            error.style.display = 'block';
            setTimeout(() => error.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            }), 50);
        }

        function clearError() {
            error.textContent = '';
            error.style.display = 'none';
        }

        function setSubmitting(isSubmitting) {
            submitBtn.disabled = isSubmitting;
            submitBtn.dataset.originalHtml ??= submitBtn.innerHTML;
            if (isSubmitting) {
                const w = submitBtn.getBoundingClientRect().width;
                submitBtn.style.width = w + 'px';
                submitBtn.innerHTML = '<span class="spinner" aria-hidden="true"></span>Saving…';
            } else {
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
                submitBtn.style.removeProperty('width');
            }
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            clearError();

            const p1 = pw.value;
            const p2 = confirmPw.value;

            if (!reqs.length(p1) || !reqs.uppercase(p1) || !reqs.lowercase(p1) || !reqs.number(p1)) {
                showError('Password must be at least 8 characters and include uppercase, lowercase, and a number.');
                return;
            }
            if (p1 !== p2) {
                showError('Passwords do not match.');
                return;
            }

            setSubmitting(true);

            // POST to this same file
            fetch('reset_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept': 'application/json'
                    },
                    body: 'password=' + encodeURIComponent(p1),
                    credentials: 'same-origin'
                })
                .then(async (r) => {
                    const data = await r.json();
                    if (!r.ok) throw new Error(data.message || 'HTTP ' + r.status);
                    return data;
                })
                .then((data) => {
                    if (data.success) {
                        // Redirect to success page upon success
                        window.location.href = 'reset_password_success.php';
                    } else {
                        // Show error message if password change fails
                        showError(data.message || 'Could not change password.');
                        setSubmitting(false);
                    }
                })
                .catch((err) => {
                    console.error('Change password failed:', err);
                    showError('Server error. Please try again.');
                    setSubmitting(false);
                });
        });
    </script>
</body>

</html>