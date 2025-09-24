<?php
// Main entry point for the website
// At the VERY TOP of your PHP file (before session_start or other code)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
error_reporting(E_ALL); // log everything, just don't display in prod

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Include patient session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

include_once $root_path . '/config/db.php';

// If already logged in, redirect to dashboard
if (is_patient_logged_in()) {
    header('Location: ../dashboard.php');
    exit;
}

// Handle flashes from redirects like ?logged_out=1 or ?expired=1
if (isset($_GET['logged_out'])) {
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'You’ve signed out successfully.'];
    header('Location: patient_login.php'); // clean URL (PRG)
    exit;
}
if (isset($_GET['expired'])) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please log in again.'];
    header('Location: patient_login.php'); // clean URL (PRG)
    exit;
}

// CSRF token generation and validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Enhanced login rate limiting with IP tracking
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_key = 'patient_login_attempts_' . hash('sha256', $client_ip);

if (!isset($_SESSION[$rate_limit_key])) $_SESSION[$rate_limit_key] = 0;
if (!isset($_SESSION['patient_last_login_attempt'])) $_SESSION['patient_last_login_attempt'] = 0;

$max_attempts = 5;
$block_seconds = 300; // 5 minutes block

// Handle POST
$error = '';
$patient_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Enhanced rate limiting check
        if ($_SESSION[$rate_limit_key] >= $max_attempts && (time() - $_SESSION['patient_last_login_attempt']) < $block_seconds) {
            $remaining = $block_seconds - (time() - $_SESSION['patient_last_login_attempt']);
            throw new RuntimeException("Too many failed attempts. Please wait " . ceil($remaining / 60) . " minutes before trying again.");
        }

        $patient_number = strtoupper(trim($_POST['username'] ?? '')); // normalize to uppercase
        $patient_number = preg_replace('/\s+/', '', $patient_number); // remove spaces just in case
        $password = $_POST['password'] ?? '';
        $posted_csrf = $_POST['csrf_token'] ?? '';

        $_SESSION['patient_last_login_attempt'] = time();

        // Validate CSRF token
        if (!hash_equals($csrf_token, $posted_csrf)) {
            throw new RuntimeException("Invalid session. Please refresh the page and try again.");
        }

        // Validate inputs
        if ($patient_number === '' || $password === '') {
            throw new RuntimeException('Please enter both Patient Number and Password.');
        } 
        
        if (!preg_match('/^P\d{6}\z/', $patient_number)) {
            usleep(500000); // Delay for invalid format
            $_SESSION[$rate_limit_key]++;
            throw new RuntimeException('Invalid Patient Number or Password.');
        }

        // Database connection check
        if (!$pdo) {
            error_log('[patient_login] Database connection failed');
            throw new RuntimeException('Service temporarily unavailable. Please try again later.');
        }

        // Query patient - using correct column names from database
        $stmt = $pdo->prepare('SELECT patient_id as id, username, password_hash as password, status FROM patients WHERE username = ? LIMIT 1');
        $stmt->execute([$patient_number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Check if account is active
            if (isset($row['status']) && strtolower($row['status']) !== 'active') {
                $_SESSION[$rate_limit_key]++;
                throw new RuntimeException('Account is inactive. Please contact your healthcare provider.');
            }

            if (password_verify($password, $row['password'])) {
                // Successful login - reset rate limit
                unset($_SESSION[$rate_limit_key], $_SESSION['patient_last_login_attempt']);
                
                // Prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['patient_id'] = $row['id'];
                $_SESSION['patient_username'] = $row['username'];
                $_SESSION['login_time'] = time();
                
                // Redirect to patient dashboard
                header('Location: ../dashboard.php');
                exit;
            } else {
                $_SESSION[$rate_limit_key]++;
                usleep(500000); // Delay for failed authentication
                throw new RuntimeException('Invalid Patient Number or Password.');
            }
        } else {
            $_SESSION[$rate_limit_key]++;
            usleep(500000); // Delay for non-existent user
            throw new RuntimeException('Invalid Patient Number or Password.');
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        error_log('[patient_login] Unexpected error: ' . $e->getMessage());
        $error = "Service temporarily unavailable. Please try again later.";
    }
}


$sessionFlash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flash = $sessionFlash ?: (!empty($error) ? ['type' => 'error', 'msg' => $error] : null);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CHO – Patient Login</title>
    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../../../assets/css/login.css" />
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
            /* green */
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

        /* Subtle inline help under inputs */
        .input-help {
            display: block;
            margin-top: 4px;
            font-size: 0.85rem;
            color: #6b7280;
            /* muted gray (Tailwind gray-500 style) */
            line-height: 1.3;
        }
    </style>
</head>

<body>
    <header class="site-header">
        <div class="logo-container" role="banner">
            <a href="../../../index.php" tabindex="0">
                <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128"
                    alt="City Health Office Koronadal logo" width="100" height="100" decoding="async" />
            </a>
        </div>
    </header>

    <main class="homepage" id="main-content">
        <section class="login-box" aria-labelledby="login-title">
            <h1 id="login-title" class="visually-hidden">Patient Login</h1>

            <form class="form active" action="patient_login.php" method="POST" novalidate>
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-header">
                    <h2>Patient Login</h2>
                </div>

                <!-- Patient Number -->
                <label for="username">Patient Number</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="input-field"
                    placeholder="Enter Patient Number (e.g., P000001)"
                    inputmode="text"
                    autocomplete="username"
                    pattern="^P\d{6}$"
                    title="Format: capital P followed by 6 digits (e.g., P000001)"
                    maxlength="7"
                    value="<?php echo htmlspecialchars($patient_number); ?>"
                    required
                    autofocus />
                <small class="input-help">
                    Format: capital “P” followed by 6 digits (e.g., P000001)
                </small>

                <!-- Password -->
                <div class="password-wrapper">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="input-field"
                        placeholder="Enter Password"
                        autocomplete="current-password"
                        required />
                    <button
                        type="button"
                        class="toggle-password"
                        aria-label="Show password"
                        aria-pressed="false"
                        title="Show/Hide Password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>


                <div class="form-footer">
                    <a href="forgot_password.php" class="forgot">Forgot Password?</a>
                </div>

                <button type="submit" class="btn">Login</button>

                <p class="alt-action">
                    Don’t have an account?
                    <a class="register-link" href="../registration/patient_registration.php">Register</a>
                </p>

                <!-- Live region for client-side validation or server messages -->
                <div class="sr-only" role="status" aria-live="polite" id="form-status"></div>
            </form>
        </section>
    </main>

    <!-- Snackbar for flash messages -->
    <div id="snackbar" role="status" aria-live="polite"></div>

    <script>
        // Password toggle (accessible & null-safe)
        (function() {
            const toggleBtn = document.querySelector(".toggle-password");
            const pwd = document.getElementById("password");
            if (!toggleBtn || !pwd) return;

            const icon = toggleBtn.querySelector("i");

            function toggle() {
                const isHidden = pwd.type === "password";
                pwd.type = isHidden ? "text" : "password";
                toggleBtn.setAttribute("aria-pressed", String(isHidden));
                toggleBtn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
                if (icon) {
                    icon.classList.toggle("fa-eye", !isHidden);
                    icon.classList.toggle("fa-eye-slash", isHidden);
                }
            }
            toggleBtn.addEventListener("click", toggle);
        })();

        // Light client validation message surface (null-safe)
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

        // Snackbar flash (with animation reset + null-safe)
        (function() {
            const el = document.getElementById('snackbar');
            if (!el) return;

            const msg = <?php echo json_encode($flash['msg']  ?? ''); ?>;
            const type = <?php echo json_encode($flash['type'] ?? ''); ?>;
            if (!msg) return;

            el.textContent = msg;
            el.classList.toggle('error', type === 'error');
            // restart animation reliably
            el.classList.remove('show');
            void el.offsetWidth; // force reflow
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 4000);
        })();
    </script>

</body>

</html>