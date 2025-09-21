<?php
// Employee forgot password with enhanced security
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

session_start();

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Redirect if already logged in
if (!empty($_SESSION['employee_id'])) {
    header('Location: ../dashboard/dashboard_' . strtolower($_SESSION['role']) . '.php');
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Enhanced rate limiting
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_key = 'employee_forgot_password_' . hash('sha256', $client_ip);

if (!isset($_SESSION[$rate_limit_key])) $_SESSION[$rate_limit_key] = 0;
if (!isset($_SESSION['employee_last_forgot_attempt'])) $_SESSION['employee_last_forgot_attempt'] = 0;

$max_attempts = 3; // Lower limit for forgot password
$block_seconds = 900; // 15 minutes block

$error = '';
$success = '';
$employee_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Rate limiting check
        if ($_SESSION[$rate_limit_key] >= $max_attempts && (time() - $_SESSION['employee_last_forgot_attempt']) < $block_seconds) {
            $remaining = $block_seconds - (time() - $_SESSION['employee_last_forgot_attempt']);
            throw new RuntimeException("Too many attempts. Please wait " . ceil($remaining / 60) . " minutes before trying again.");
        }

        $employee_id = strtoupper(trim($_POST['employee_id'] ?? ''));
        $posted_csrf = $_POST['csrf_token'] ?? '';

        $_SESSION['employee_last_forgot_attempt'] = time();

        // Validate CSRF token
        if (!hash_equals($csrf_token, $posted_csrf)) {
            throw new RuntimeException("Invalid session. Please refresh the page and try again.");
        }

        // Validate input
        if ($employee_id === '') {
            throw new RuntimeException('Please enter your Employee ID.');
        }

        if (!preg_match('/^E\d{6}$/', $employee_id)) {
            $_SESSION[$rate_limit_key]++;
            usleep(500000); // Delay for invalid format
            throw new RuntimeException('Invalid Employee ID format.');
        }

        // Database connection check
        if (!$pdo) {
            error_log('[employee_forgot_password] Database connection failed');
            throw new RuntimeException('Service temporarily unavailable. Please try again later.');
        }

        // Query employee - always delay the same amount of time regardless of result
        $start_time = microtime(true);
        
        $stmt = $pdo->prepare('SELECT id, employee_id, email, first_name, last_name, status FROM employees WHERE employee_id = ? AND status = "active" LIMIT 1');
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        // Consistent timing to prevent user enumeration
        $elapsed = microtime(true) - $start_time;
        $target_time = 0.5; // 500ms target
        if ($elapsed < $target_time) {
            usleep(($target_time - $elapsed) * 1000000);
        }

        if ($employee) {
            // Generate OTP
            $otp = sprintf('%06d', mt_rand(100000, 999999));
            $otp_expiry = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            
            // Store OTP in database
            $stmt = $pdo->prepare('UPDATE employees SET reset_otp = ?, reset_otp_expiry = ? WHERE id = ?');
            $stmt->execute([$otp, $otp_expiry, $employee['id']]);

            // Send email
            $mail = new PHPMailer(true);
            
            try {
                // Email configuration (you'll need to set these)
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Configure your SMTP
                $mail->SMTPAuth = true;
                $mail->Username = 'your-email@gmail.com'; // Configure
                $mail->Password = 'your-app-password'; // Configure
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('cho.koronadal@gmail.com', 'CHO Koronadal');
                $mail->addAddress($employee['email'], $employee['first_name'] . ' ' . $employee['last_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset OTP - CHO Employee Portal';
                $mail->Body = "
                    <h2>Password Reset Request</h2>
                    <p>Dear {$employee['first_name']},</p>
                    <p>You have requested to reset your password. Use the following OTP to proceed:</p>
                    <h3 style='color: #007bff; font-size: 24px; letter-spacing: 3px;'>{$otp}</h3>
                    <p><strong>This OTP is valid for 15 minutes only.</strong></p>
                    <p>If you did not request this, please ignore this email and contact IT support.</p>
                    <hr>
                    <p><small>CHO Koronadal Employee Portal</small></p>
                ";

                $mail->send();
                
                // Success - redirect to OTP verification page
                $_SESSION['reset_employee_id'] = $employee_id;
                unset($_SESSION[$rate_limit_key], $_SESSION['employee_last_forgot_attempt']);
                
                header('Location: employee_forgot_password_otp.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[employee_forgot_password] Email send failed: ' . $e->getMessage());
                throw new RuntimeException('Failed to send reset email. Please try again later or contact support.');
            }
        } else {
            // Invalid employee ID - increment rate limit but show generic message
            $_SESSION[$rate_limit_key]++;
            // Still show success message to prevent user enumeration
            $success = 'If this Employee ID exists, a reset email has been sent.';
        }
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        error_log('[employee_forgot_password] Unexpected error: ' . $e->getMessage());
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
    <title>Forgot Password - CHO Employee Portal</title>
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

        .input-help {
            display: block;
            margin-top: 4px;
            font-size: 0.85rem;
            color: #6b7280;
            line-height: 1.3;
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
        <section class="login-box" aria-labelledby="forgot-title">
            <h1 id="forgot-title" class="visually-hidden">Employee Forgot Password</h1>

            <form class="form active" action="employee_forgot_password.php" method="POST" novalidate>
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-header">
                    <h2>Reset Password</h2>
                    <p style="color: #666; font-size: 0.9rem; margin-top: 8px;">
                        Enter your Employee ID to receive a password reset code.
                    </p>
                </div>

                <!-- Employee ID -->
                <label for="employee_id">Employee ID</label>
                <input
                    type="text"
                    id="employee_id"
                    name="employee_id"
                    class="input-field"
                    placeholder="Enter Employee ID (e.g., E000001)"
                    inputmode="text"
                    autocomplete="username"
                    pattern="^E\d{6}$"
                    title="Format: capital E followed by 6 digits (e.g., E000001)"
                    maxlength="7"
                    value="<?php echo htmlspecialchars($employee_id); ?>"
                    required
                    autofocus />
                <small class="input-help">
                    Format: capital "E" followed by 6 digits (e.g., E000001)
                </small>

                <button type="submit" class="btn">Send Reset Code</button>

                <p class="alt-action">
                    Remember your password?
                    <a class="register-link" href="employee_login.php">Back to Login</a>
                </p>

                <!-- Live region for client-side validation or server messages -->
                <div class="sr-only" role="status" aria-live="polite" id="form-status"></div>
            </form>
        </section>
    </main>

    <!-- Snackbar for flash messages -->
    <div id="snackbar" role="status" aria-live="polite"></div>

    <script>
        // Light client validation message surface
        (function() {
            const form = document.querySelector("form");
            const status = document.getElementById("form-status");
            if (!form || !status) return;

            form.addEventListener("submit", function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    status.textContent = "Please fix the highlighted fields.";
                }
            });
        })();

        // Snackbar flash
        (function() {
            const el = document.getElementById('snackbar');
            if (!el) return;

            const msg = <?php echo json_encode($flash['msg']  ?? ''); ?>;
            const type = <?php echo json_encode($flash['type'] ?? ''); ?>;
            if (!msg) return;

            el.textContent = msg;
            el.classList.toggle('error', type === 'error');
            el.classList.remove('show');
            void el.offsetWidth;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 4000);
        })();
    </script>
</body>
</html>