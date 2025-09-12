<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $inputOTP = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    if (!$inputOTP) {
        echo json_encode(['success' => false, 'message' => 'OTP is required.']);
        exit;
    }
    if (!isset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_otp_time'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
        exit;
    }
    $sessionOTP = $_SESSION['reset_otp'];
    $otpTime = $_SESSION['reset_otp_time'];
    $expirySeconds = 300; // 5 minutes
    if (time() - $otpTime > $expirySeconds) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time']);
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new code.']);
        exit;
    }
    if ($inputOTP === $sessionOTP) {
        // OTP verified, allow password reset
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time']); // Optionally, keep user_id for next step
        $_SESSION['otp_verified_for_reset'] = true;
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
        exit;
    }
}
// For GET requests, show HTML page below
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../../assets/css/login.css" />
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

        .registration-box {
            width: 100%;
            min-width: 350px;
            max-width: 400px;
            background: var(--surface);
            border-radius: 16px;
            padding: 24px 22px 28px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative;
        }

        .form {
            display: flex;
            flex-direction: column;
            gap: 18px;
            width: 100%;
        }

        label {
            display: block;
            text-align: left;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
        }

        .input-field {
            height: 44px;
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: var(--focus-ring);
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
            transition: transform 0.12s, box-shadow 0.12s, background-color 0.12s;
        }

        .btn:hover {
            box-shadow: 0 6px 16px rgba(0, 123, 255, 0.25);
            background-color: var(--brand-600);
            transform: translateY(-1px);
        }

        .btn:focus-visible {
            outline: none;
            box-shadow: var(--focus-ring);
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
            <h2 class="otp-title">Verify OTP for Password Reset</h2>
            <div class="otp-instructions">
                Please enter the One-Time Password (OTP) sent to your email address to continue resetting your password.
            </div>
            <form class="otp-form" id="otpForm" autocomplete="off" novalidate>
                <input type="text" maxlength="6" class="otp-input" id="otp" name="otp" placeholder="Enter OTP" required
                    inputmode="numeric" pattern="\d{6}" autocomplete="one-time-code" />
                <div id="errorMsg" class="error" role="alert" aria-live="polite" style="display:none"></div>
                <div style="text-align:center; margin-top:18px;">
                    <div style="display:flex; justify-content:center; gap:12px; margin-top:0;">
                        <button id="backBtn" type="button" class="btn"
                            style="background:#eee; color:#333; padding:8px 24px; border-radius:4px; text-decoration:none; border:none;">Back to Login</button>
                        <button id="reviewBtn" type="button" class="btn"
                            style="background:#ffc107; color:#333; padding:8px 24px; border-radius:4px; text-decoration:none; border:none;">Change Email / Patient ID</button>
                        <button id="submitBtn" type="submit" class="btn"
                            style="background:#007bff; color:#fff; padding:8px 24px; border-radius:4px; text-decoration:none; border:none;">Verify OTP <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </div>
            </form>
            <div style="text-align:center; margin-top:12px; font-size:0.95em; color:#555;">
                Didn't receive the code?
                <button class="resend-link" id="resendBtn" type="button">Resend OTP</button>
            </div>
    </section>
    <script>
        (function() {
            const backBtn = document.getElementById('backBtn');
            backBtn.addEventListener('click', function() {
                window.location.href = 'patientLogin.php';
            });
            const form = document.getElementById('otpForm');
            const input = document.getElementById('otp');
            const errorMsg = document.getElementById('errorMsg');
            const submitBtn = document.getElementById('submitBtn');
            const resendBtn = document.getElementById('resendBtn');
            const reviewBtn = document.getElementById('reviewBtn');
            reviewBtn.addEventListener('click', function() {
                window.location.href = 'forgotPassword.html';
            });
            input.focus();

            function showError(msg) {
                errorMsg.textContent = msg;
                errorMsg.style.display = 'block';
                setTimeout(() => {
                    errorMsg.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 50);
            }

            function clearError() {
                errorMsg.textContent = '';
                errorMsg.style.display = 'none';
            }

            function setSubmitting(isSubmitting) {
                submitBtn.disabled = isSubmitting;
                submitBtn.dataset.originalText ??= submitBtn.textContent;
                submitBtn.textContent = isSubmitting ? 'Verifying…' : submitBtn.dataset.originalText;
            }
            input.addEventListener('input', () => {
                const v = input.value.replace(/\D+/g, '').slice(0, 6);
                if (v !== input.value) input.value = v;
            });
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                clearError();
                const otp = (input.value || '').trim();
                if (!/^\d{6}$/.test(otp)) {
                    showError('Please enter the 6-digit code.');
                    input.focus();
                    return;
                }
                setSubmitting(true);
                fetch('verifyOTPPassword.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        body: 'otp=' + encodeURIComponent(otp),
                        credentials: 'same-origin'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'changePassword.html';
                        } else {
                            showError(data.message || 'Invalid OTP.');
                            setSubmitting(false);
                        }
                    })
                    .catch(() => {
                        showError('Server error. Please try again.');
                        setSubmitting(false);
                    });
            });
            // Resend OTP button logic with 30s cooldown
            let resendCooldown = 30;
            let resendTimer = null;

            function setResendDisabled(disabled) {
                resendBtn.disabled = disabled;
                resendBtn.style.opacity = disabled ? 0.6 : 1;
                resendBtn.style.pointerEvents = disabled ? 'none' : 'auto';
            }

            function startResendCountdown() {
                setResendDisabled(true);
                let seconds = resendCooldown;
                resendBtn.textContent = `Resend OTP (${seconds})`;
                resendTimer = setInterval(() => {
                    seconds--;
                    resendBtn.textContent = `Resend OTP (${seconds})`;
                    if (seconds <= 0) {
                        clearInterval(resendTimer);
                        resendBtn.textContent = 'Resend OTP';
                        setResendDisabled(false);
                    }
                }, 1000);
            }
            resendBtn.addEventListener('click', function() {
                startResendCountdown();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            });
            startResendCountdown();
        })();
    </script>
</body>

</html>