<?php
// forgot_password_otp.php
declare(strict_types=1);

session_start();

// Flash message from previous page
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']); // one-time use

// DEV: show errors (turn off in prod)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Guard: if user lands here via GET without a valid reset context,
 * send them back to the entry page.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_otp'])) {
        header('Location: forgot_password.php', true, 303);
        exit;
    }
}

/**
 * AJAX OTP verify endpoint (same file)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    $inputOTP = isset($_POST['otp']) ? trim((string)$_POST['otp']) : '';
    if ($inputOTP === '') {
        echo json_encode(['success' => false, 'message' => 'OTP is required.']);
        exit;
    }

    if (!isset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_otp_time'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please request a new code.']);
        exit;
    }

    $sessionOTP     = (string)$_SESSION['reset_otp'];
    $otpTime        = (int)$_SESSION['reset_otp_time'];
    $expirySeconds  = 300; // 5 minutes

    if ((time() - $otpTime) > $expirySeconds) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time']);
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new code.']);
        exit;
    }

    if (hash_equals($sessionOTP, $inputOTP)) {
        // Mark session as verified for next step
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time']);
        $_SESSION['otp_verified_for_reset'] = true;
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify OTP • Forgot Password</title>
    <link rel="stylesheet" href="../../assets/css/login.css" />
    <style>
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
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
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
            min-height: 100vh
        }

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
            background-repeat: no-repeat
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 14px 0
        }

        .logo {
            width: 100px;
            height: auto;
            transition: transform .2s ease
        }

        .logo:hover {
            transform: scale(1.04)
        }

        .otp-section {
            min-height: 100vh;
            display: grid;
            place-items: start center;
            padding: 160px 16px 40px
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
            position: relative
        }

        .otp-title {
            margin: 0 0 18px 0;
            font-size: 1.4rem;
            color: var(--text);
            text-align: center
        }

        .otp-instructions {
            color: var(--muted);
            font-size: 1rem;
            margin-bottom: 18px
        }

        .otp-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
            align-items: center
        }

        .otp-input {
            letter-spacing: .4em;
            font-size: 1.5rem;
            text-align: center;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease
        }

        .otp-input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: var(--focus-ring)
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
            transition: transform .12s ease, box-shadow .12s ease, background-color .12s ease
        }

        .btn.secondary {
            background-color: #e5e7eb;
            color: #111827
        }

        .btn:hover,
        .btn.secondary:hover {
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

        .success {
            display: none;
            margin: .6rem 0;
            padding: .65rem .75rem;
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            border-radius: 10px;
            margin-bottom: 18px
        }

        .resend-link {
            background: none;
            border: none;
            color: var(--brand);
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            font-size: .85rem;
            margin-top: 10px
        }

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
            z-index: 99999;
            /* very high to sit on top */
            font: 600 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        #snackbar.error {
            background: #dc2626;
        }

        #snackbar.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }


        @media (prefers-reduced-motion: reduce) {

            .logo,
            .btn,
            .otp-input {
                transition: none
            }
        }

        /* Button spinner (re-use from your other page if desired) */
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

    <section class="otp-section">
        <div class="otp-box">
            <h2 class="otp-title">Verify OTP for Password Reset</h2>
            <div class="otp-instructions">
                Please enter the One-Time Password (OTP) sent to your email address to continue resetting your password.
            </div>

            <form class="otp-form" id="otpForm" autocomplete="one-time-code" novalidate>
                <input type="text" maxlength="6" class="otp-input" id="otp" name="otp" placeholder="Enter OTP" required
                    inputmode="numeric" pattern="\d{6}" />
                <div id="errorMsg" class="error" role="alert" aria-live="polite" style="display:none"></div>

                <div style="text-align:center; margin-top:18px;">
                    <div style="display:flex; justify-content:center; gap:12px; margin-top:0;">
                        <button id="backBtn" type="button" class="btn secondary"
                            style="background:#eee; color:#333;">← Back</button>
                        <button id="reviewBtn" type="button" class="btn" style="background:#ffc107; color:#333;">Change Email/ID</button>
                        <button id="submitBtn" type="submit" class="btn">Verify OTP</button>
                    </div>
                </div>
            </form>

            <div style="text-align:center; margin-top:12px; font-size:0.95em; color:#555;">
                Didn't receive the code?
                <button class="resend-link" id="resendBtn" type="button">Resend OTP</button>
            </div>
        </div>
    </section>

    <div id="snackbar" role="status" aria-live="polite" aria-atomic="true"></div>

    <script>
        (function() {
            // --- cache DOM
            const form = document.getElementById('otpForm');
            const input = document.getElementById('otp');
            const errorMsg = document.getElementById('errorMsg');
            const submitBtn = document.getElementById('submitBtn');
            const backBtn = document.getElementById('backBtn');
            const reviewBtn = document.getElementById('reviewBtn');
            const resendBtn = document.getElementById('resendBtn');
            const snackbar = document.getElementById('snackbar');

            // --- nav
            backBtn?.addEventListener('click', () => {
                window.location.href = 'forgot_password.php';
            });
            reviewBtn?.addEventListener('click', () => {
                window.location.href = 'forgot_password.php';
            });

            // --- snackbar util (robust)
            function showSnack(msg, isError = false) {
                if (!snackbar) return;
                snackbar.textContent = msg;
                snackbar.classList.toggle('error', !!isError);
                snackbar.classList.remove('show'); // reset
                void snackbar.offsetWidth; // force reflow so animation restarts
                snackbar.classList.add('show');
                setTimeout(() => snackbar.classList.remove('show'), 4000);
            }

            // --- form helpers
            function showError(msg) {
                errorMsg.textContent = msg;
                errorMsg.style.display = 'block';
            }

            function clearError() {
                errorMsg.textContent = '';
                errorMsg.style.display = 'none';
            }

            function setSubmitting(b) {
                submitBtn.disabled = b;
                submitBtn.dataset.originalHtml ??= submitBtn.innerHTML;
                if (b) {
                    const w = submitBtn.getBoundingClientRect().width;
                    submitBtn.style.width = w + 'px';
                    submitBtn.innerHTML = '<span class="spinner"></span>Verifying…';
                } else {
                    submitBtn.innerHTML = submitBtn.dataset.originalHtml;
                    submitBtn.style.removeProperty('width');
                }
            }

            // --- OTP input behavior
            input?.focus();
            input?.addEventListener('input', () => {
                const v = input.value.replace(/\D+/g, '').slice(0, 6);
                if (v !== input.value) input.value = v;
                if (v.length === 6) form.requestSubmit();
            });

            // --- verify OTP (posts to this same file)
            form?.addEventListener('submit', (e) => {
                e.preventDefault();
                clearError();

                const otp = (input.value || '').trim();
                if (!/^\d{6}$/.test(otp)) {
                    showError('Please enter the 6-digit code.');
                    input.focus();
                    return;
                }

                setSubmitting(true);

                fetch('forgot_password_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        body: 'otp=' + encodeURIComponent(otp),
                        credentials: 'same-origin'
                    })
                    .then(async r => {
                        const txt = await r.text();
                        let data;
                        try {
                            data = JSON.parse(txt);
                        } catch {
                            data = null;
                        }
                        if (!r.ok) throw new Error((data && data.message) || ('HTTP ' + r.status));
                        return data || {};
                    })
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'reset_password.php';
                        } else {
                            showError(data.message || 'Invalid OTP.');
                            setSubmitting(false);
                        }
                    })
                    .catch(err => {
                        console.error('OTP verify failed:', err);
                        showError('Server error. Please try again.');
                        setSubmitting(false);
                    });
            });

            // --- resend OTP (ABSOLUTE PATH to actions file)
            const RESEND_URL = './resend_otp.php';

            let cooldown = 30,
                timerId = null;

            function setResendDisabled(d) {
                resendBtn.disabled = d;
                resendBtn.style.opacity = d ? 0.6 : 1;
                resendBtn.style.pointerEvents = d ? 'none' : 'auto';
            }

            function startResendCountdown() {
                if (timerId) clearInterval(timerId);
                let s = cooldown;
                setResendDisabled(true);
                resendBtn.textContent = `Resend OTP (${s})`;
                timerId = setInterval(() => {
                    s--;
                    resendBtn.textContent = `Resend OTP (${s})`;
                    if (s <= 0) {
                        clearInterval(timerId);
                        timerId = null;
                        resendBtn.textContent = 'Resend OTP';
                        setResendDisabled(false);
                    }
                }, 1000);
            }

            resendBtn?.addEventListener('click', () => {
                setResendDisabled(true);
                fetch(RESEND_URL, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                    .then(async r => {
                        const txt = await r.text();
                        let data;
                        try {
                            data = JSON.parse(txt);
                        } catch {
                            data = null;
                        }
                        if (!r.ok) throw new Error((data && data.message) || ('HTTP ' + r.status));
                        return data || {};
                    })
                    .then(data => {
                        // Show server's message in snackbar regardless of success/fail
                        showSnack(data.message || (data.success ? 'New OTP sent.' : 'Could not resend OTP.'), !data.success);
                        startResendCountdown();
                    })
                    .catch(err => {
                        console.error('Resend OTP failed:', err);
                        showSnack('Network error. Please try again.', true);
                        // allow immediate retry on true network error
                        setResendDisabled(false);
                    });
            });

            // initial cooldown on page load
            startResendCountdown();

            // --- show flash from PHP (if any)
            (function() {
                const msg = <?php echo json_encode($flash['msg']  ?? ''); ?>;
                const type = <?php echo json_encode($flash['type'] ?? ''); ?>;
                if (msg) showSnack(msg, type === 'error');
            })();
        })();
    </script>
</body>

</html>