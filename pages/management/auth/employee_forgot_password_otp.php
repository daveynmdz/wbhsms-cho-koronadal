<?php
// Employee OTP verification for password reset
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

// Check if we have reset session data
if (empty($_SESSION['reset_otp']) || empty($_SESSION['reset_user_id'])) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg' => 'Invalid session. Please start the password reset process again.'
    ];
    header('Location: employee_forgot_password.php');
    exit;
}

// Check OTP expiry (15 minutes)
$otp_time = $_SESSION['reset_otp_time'] ?? 0;
if (time() - $otp_time > 900) {
    unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_name'], $_SESSION['reset_otp_time']);
    $_SESSION['flash'] = [
        'type' => 'error',
        'msg' => 'OTP has expired. Please request a new password reset.'
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
        $entered_otp = trim($_POST['otp'] ?? '');
        $posted_csrf = $_POST['csrf_token'] ?? '';

        // Validate CSRF token
        if (!hash_equals($csrf_token, $posted_csrf)) {
            throw new RuntimeException("Invalid session. Please refresh the page and try again.");
        }

        // Validate OTP
        if ($entered_otp === '') {
            throw new RuntimeException('Please enter the OTP code.');
        }

        if (!preg_match('/^\d{6}$/', $entered_otp)) {
            throw new RuntimeException('OTP must be 6 digits.');
        }

        // Check OTP match
        if ($entered_otp !== $_SESSION['reset_otp']) {
            throw new RuntimeException('Invalid OTP code. Please check your email and try again.');
        }

        // OTP is valid - redirect to new password page
        $_SESSION['reset_otp_verified'] = true;
        header('Location: employee_reset_password.php');
        exit;

    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        error_log('[employee_forgot_password_otp] Unexpected error: ' . $e->getMessage());
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
    <title>Enter OTP - CHO Employee Portal</title>
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

        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: 600;
        }

        .countdown {
            text-align: center;
            margin: 10px 0;
            font-size: 0.9rem;
            color: #666;
        }

        .countdown.warning {
            color: #dc2626;
            font-weight: 600;
        }

        #resendBtn:disabled {
            color: #999;
            cursor: not-allowed;
            text-decoration: none;
        }

        .resend-cooldown {
            font-size: 0.8rem;
            color: #666;
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
        <section class="login-box" aria-labelledby="otp-title">
            <h1 id="otp-title" class="visually-hidden">Enter OTP Code</h1>

            <form class="form active" action="employee_forgot_password_otp.php" method="POST" novalidate>
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-header">
                    <h2>Enter Verification Code</h2>
                    <p style="color: #666; font-size: 0.9rem; margin-top: 8px; margin-bottom: 0;">
                        We've sent a 6-digit code to:<br>
                        <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong>
                    </p>
                </div>

                <!-- OTP Input -->
                <label for="otp">Verification Code</label>
                <input
                    type="text"
                    id="otp"
                    name="otp"
                    class="input-field otp-input"
                    placeholder="000000"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="\d{6}"
                    maxlength="6"
                    required
                    autofocus />
                
                <!-- Countdown Timer -->
                <div id="countdown" class="countdown"></div>

                <button type="submit" class="btn">Verify Code</button>

                <p class="alt-action" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                    <a class="register-link" href="employee_forgot_password.php">← Back to Password Reset</a>
                    <button type="button" id="resendBtn" class="register-link" style="background: none; border: none; cursor: pointer; color: #007bff; text-decoration: underline;">
                        Resend OTP
                    </button>
                </p>

                <!-- Live region for client-side validation or server messages -->
                <div class="sr-only" role="status" aria-live="polite" id="form-status"></div>
            </form>
        </section>
    </main>

    <!-- Snackbar for flash messages -->
    <div id="snackbar" role="status" aria-live="polite"></div>

    <script>
        // OTP input formatting and countdown timer
        (function() {
            const otpInput = document.getElementById('otp');
            const countdown = document.getElementById('countdown');
            const form = document.querySelector('form');
            
            // Auto-format OTP input
            if (otpInput) {
                otpInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 6) value = value.substring(0, 6);
                    e.target.value = value;
                });

                // Auto-submit when 6 digits entered
                otpInput.addEventListener('input', function(e) {
                    if (e.target.value.length === 6) {
                        form.submit();
                    }
                });
            }

            // Countdown timer
            const otpTime = <?php echo $_SESSION['reset_otp_time'] ?? 0; ?>;
            const expiryTime = otpTime + 900; // 15 minutes
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = expiryTime - now;
                
                if (remaining <= 0) {
                    countdown.innerHTML = '<span class="warning">⚠️ Code expired. Please request a new reset.</span>';
                    otpInput.disabled = true;
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                countdown.textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (remaining <= 300) { // Last 5 minutes
                    countdown.classList.add('warning');
                }
                
                setTimeout(updateCountdown, 1000);
            }
            
            updateCountdown();
        })();

        // Resend OTP functionality
        (function() {
            const resendBtn = document.getElementById('resendBtn');
            if (!resendBtn) return;

            let resendCooldown = 60; // 60 seconds cooldown
            let cooldownTimer = null;

            function startCooldown() {
                resendBtn.disabled = true;
                resendBtn.innerHTML = `Resend OTP (<span class="resend-cooldown">${resendCooldown}s</span>)`;
                
                cooldownTimer = setInterval(() => {
                    resendCooldown--;
                    if (resendCooldown <= 0) {
                        clearInterval(cooldownTimer);
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend OTP';
                        resendCooldown = 60;
                    } else {
                        resendBtn.innerHTML = `Resend OTP (<span class="resend-cooldown">${resendCooldown}s</span>)`;
                    }
                }, 1000);
            }

            resendBtn.addEventListener('click', function() {
                if (resendBtn.disabled) return;

                // Send AJAX request to resend OTP
                fetch('employee_forgot_password_resend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        csrf_token: '<?php echo $csrf_token; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        const snackbar = document.getElementById('snackbar');
                        snackbar.textContent = data.message || 'New OTP sent to your email!';
                        snackbar.classList.remove('error');
                        snackbar.classList.remove('show');
                        void snackbar.offsetWidth;
                        snackbar.classList.add('show');
                        setTimeout(() => snackbar.classList.remove('show'), 4000);
                        
                        startCooldown();
                    } else {
                        // Show error message
                        const snackbar = document.getElementById('snackbar');
                        snackbar.textContent = data.message || 'Failed to resend OTP. Please try again.';
                        snackbar.classList.add('error');
                        snackbar.classList.remove('show');
                        void snackbar.offsetWidth;
                        snackbar.classList.add('show');
                        setTimeout(() => snackbar.classList.remove('show'), 4000);
                    }
                })
                .catch(error => {
                    console.error('Resend error:', error);
                    const snackbar = document.getElementById('snackbar');
                    snackbar.textContent = 'Failed to resend OTP. Please try again.';
                    snackbar.classList.add('error');
                    snackbar.classList.remove('show');
                    void snackbar.offsetWidth;
                    snackbar.classList.add('show');
                    setTimeout(() => snackbar.classList.remove('show'), 4000);
                });
            });
        })();

        // Form validation
        (function() {
            const form = document.querySelector("form");
            const status = document.getElementById("form-status");
            
            if (!form || !status) return;

            form.addEventListener("submit", function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    status.textContent = "Please enter a valid 6-digit code.";
                }
            });
        })();

        // Snackbar flash messages
        (function() {
            const el = document.getElementById('snackbar');
            if (!el) return;

            const msg = <?php echo json_encode($flash['msg'] ?? ''); ?>;
            const type = <?php echo json_encode($flash['type'] ?? ''); ?>;
            if (!msg) return;

            el.textContent = msg;
            el.classList.toggle('error', type === 'error');
            el.classList.remove('show');
            void el.offsetWidth;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 6000);
        })();
    </script>
</body>
</html>