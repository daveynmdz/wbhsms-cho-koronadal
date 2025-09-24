<?php
// Employee new password setting after OTP verification
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Include employee session configuration
require_once __DIR__ . '/../../../config/session/employee_session.php';
require_once __DIR__ . '/../../../config/db.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Redirect if already logged in
if (!empty($_SESSION['employee_id'])) {
    $role = strtolower($_SESSION['role']);
    header('Location: ../' . $role . '/dashboard.php');
    exit;
}

// Check if OTP was verified
if (empty($_SESSION['reset_otp_verified']) || empty($_SESSION['reset_user_id'])) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg' => 'Your password reset session has expired. Please start the password reset process again.'
    ];
    header('Location: employee_forgot_password.php');
    exit;
}

// Check if reset session is too old (30 minutes timeout)
$reset_time = $_SESSION['reset_otp_time'] ?? 0;
if (time() - $reset_time > 1800) { // 30 minutes
    // Clear expired session data
    unset(
        $_SESSION['reset_otp'],
        $_SESSION['reset_user_id'], 
        $_SESSION['reset_email'],
        $_SESSION['reset_name'],
        $_SESSION['reset_otp_time'],
        $_SESSION['reset_otp_verified']
    );
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg' => 'Your password reset session has expired for security. Please start over.'
    ];
    header('Location: employee_forgot_password.php');
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $posted_csrf = $_POST['csrf_token'] ?? '';

        // Validate CSRF token
        if (!hash_equals($csrf_token, $posted_csrf)) {
            throw new RuntimeException("Invalid session. Please refresh the page and try again.");
        }

        // Validate passwords
        if ($new_password === '' || $confirm_password === '') {
            throw new RuntimeException('Please enter and confirm your new password.');
        }

        if ($new_password !== $confirm_password) {
            throw new RuntimeException('Passwords do not match. Please try again.');
        }

        // Password strength validation
        if (strlen($new_password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters long.');
        }

        // Strength check (match patient system)
        $isStrong =
            strlen($new_password) >= 8 &&
            preg_match('/[A-Z]/', $new_password) &&
            preg_match('/[a-z]/', $new_password) &&
            preg_match('/[0-9]/', $new_password);

        if (!$isStrong) {
            throw new RuntimeException('Password must be at least 8 characters and include uppercase, lowercase, and a number.');
        }

        // Database connection check
        if (!$pdo) {
            error_log('[employee_reset_password] Database connection failed');
            throw new RuntimeException('Service temporarily unavailable. Please try again later.');
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in database
        $stmt = $pdo->prepare('UPDATE employees SET password = ?, updated_at = NOW() WHERE employee_id = ?');
        $result = $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);

        if (!$result) {
            throw new RuntimeException('Failed to update password. Please try again.');
        }

        // Clear reset session data
        unset(
            $_SESSION['reset_otp'],
            $_SESSION['reset_user_id'], 
            $_SESSION['reset_email'],
            $_SESSION['reset_name'],
            $_SESSION['reset_otp_time'],
            $_SESSION['reset_otp_verified']
        );

        // Set success message and redirect
        $_SESSION['flash'] = [
            'type' => 'success',
            'msg' => 'Password reset successful! You can now log in with your new password.'
        ];
        
        header('Location: employee_reset_password_success.php');
        exit;

    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        error_log('[employee_reset_password] Unexpected error: ' . $e->getMessage());
        $error = "Service temporarily unavailable. Please try again later.";
    }
}

// Handle flash messages
$sessionFlash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flash = $sessionFlash ?: (!empty($error) ? array('type' => 'error', 'msg' => $error) : (!empty($success) ? array('type' => 'success', 'msg' => $success) : null));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - CHO Employee Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/login.css">
    <style>
        /* Snackbar */
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
            box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
            opacity: 0;
            pointer-events: none;
            transition: transform .25s ease, opacity .25s ease;
            z-index: 9999;
            font: 600 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        #snackbar.error {
            background: #dc2626;
        }

        #snackbar.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .password-requirements {
            padding: 12px 14px;
            list-style: none;
            font-size: .95em;
            color: #555;
            margin: 10px 0 20px;
            background: #f8fafc;
            border: 1px dashed #ced4da;
            border-radius: 10px;
            text-align: left;
        }

        .password-requirements h4 {
            margin: 0 0 10px;
            font-size: 1rem;
        }

        .password-requirements li {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon.green {
            color: green;
        }

        .icon.red {
            color: red;
        }

        .password-wrapper {
            position: relative;
            display: grid;
        }

        .password-wrapper .input-field {
            padding-right: 42px;
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
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="logo-container" role="banner">
            <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128"
                alt="City Health Office Koronadal logo" width="100" height="100" decoding="async" />
        </div>
    </header>

    <main class="homepage" id="main-content">
        <section class="login-box" aria-labelledby="reset-title">
            <h1 id="reset-title" class="visually-hidden">Set New Password</h1>

            <form class="form active" action="employee_reset_password.php" method="POST" novalidate>
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-header">
                    <h2>Set New Password</h2>
                    <p style="color: #666; font-size: 0.9rem; margin-top: 8px; margin-bottom: 0;">
                        Create a strong password for your employee account.
                    </p>
                </div>

                <!-- New Password -->
                <div class="password-wrapper">
                    <label for="password">New Password*</label>
                    <input type="password" id="password" name="new_password" class="input-field" required autocomplete="new-password" aria-describedby="pw-req" />
                    <i class="fa-solid fa-eye toggle-password" aria-hidden="true"></i>
                </div>

                <!-- Confirm Password -->
                <div class="password-wrapper">
                    <label for="confirm-password">Confirm New Password*</label>
                    <input type="password" id="confirm-password" name="confirm_password" class="input-field" required autocomplete="new-password" />
                    <i class="fa-solid fa-eye toggle-password" aria-hidden="true"></i>
                </div>

                <!-- Password Requirements -->
                <ul class="password-requirements" id="password-requirements">
                    <h4>Password Requirements:</h4>
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

                <!-- Live region for client-side validation or server messages -->
                <div class="sr-only" role="status" aria-live="polite" id="form-status"></div>
            </form>
        </section>
    </main>

    <!-- Snackbar for flash messages -->
    <div id="snackbar" role="status" aria-live="polite"></div>

    <script>
        const $ = (sel, ctx = document) => ctx.querySelector(sel);
        const pw = $('#password');
        const confirmPw = $('#confirm-password');
        const form = document.querySelector('form');
        const error = document.getElementById('error');
        const submitBtn = document.getElementById('submitBtn');
        const backBtn = document.getElementById('backBtn');

        backBtn.addEventListener('click', () => {
            window.location.href = 'employee_login.php';
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

        // Live requirements validation
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

            // If validation passes, submit the form normally
            setSubmitting(true);
            form.submit();
        });

        // Add spinner CSS
        const style = document.createElement('style');
        style.textContent = `
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
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>