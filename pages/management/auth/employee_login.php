<?php
// Main entry point for employee authentication
// At the VERY TOP of your PHP file (before session_start or other code)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

include_once $root_path . '/config/db.php';

// If already logged in, redirect to appropriate dashboard
if (!empty($_SESSION['employee_id'])) {
    $role = strtolower(trim($_SESSION['role'] ?? ''));
    $dashboardMap = [
        'admin' => '../admin/dashboard.php',
        'administrator' => '../admin/dashboard.php',
        'doctor' => '../doctor/dashboard.php',
        'nurse' => '../nurse/dashboard.php',
        'pharmacist' => '../pharmacist/dashboard.php',
        'bhw' => '../bhw/dashboard.php',
        'barangay health worker' => '../bhw/dashboard.php',
        'dho' => '../dho/dashboard.php',
        'district health officer' => '../dho/dashboard.php',
        'records officer' => '../records_officer/dashboard.php',
        'cashier' => '../cashier/dashboard.php',
        'laboratory tech.' => '../laboratory_tech/dashboard.php',
        'laboratory technician' => '../laboratory_tech/dashboard.php',
        'lab tech' => '../laboratory_tech/dashboard.php'
    ];
    $redirect = $dashboardMap[$role] ?? '../dashboard/dashboard_admin.php';
    header('Location: ' . $redirect);
    exit;
}

// Handle flashes from redirects
if (isset($_GET['logged_out'])) {
    $_SESSION['flash'] = array('type' => 'success', 'msg' => 'You have signed out successfully.');
    // Don't redirect again, just continue with the current page load
}
if (isset($_GET['expired'])) {
    $_SESSION['flash'] = array('type' => 'error', 'msg' => 'Your session expired. Please log in again.');
    header('Location: employee_login.php');
    exit;
}

// CSRF token generation and validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Enhanced login rate limiting with IP tracking
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_key = 'login_attempts_' . hash('sha256', $client_ip);

if (!isset($_SESSION[$rate_limit_key])) $_SESSION[$rate_limit_key] = 0;
if (!isset($_SESSION['last_login_attempt'])) $_SESSION['last_login_attempt'] = 0;

$max_attempts = 5; // Reduced for better security
$block_seconds = 300; // 5 minutes block

$error = '';
$employee_number = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Enhanced rate limiting check
        if ($_SESSION[$rate_limit_key] >= $max_attempts && (time() - $_SESSION['last_login_attempt']) < $block_seconds) {
            $remaining = $block_seconds - (time() - $_SESSION['last_login_attempt']);
            throw new RuntimeException("Too many failed attempts. Please wait " . ceil($remaining / 60) . " minutes before trying again.");
        }

        $employee_number = trim($_POST['employee_number'] ?? '');
        $plain_password = $_POST['password'] ?? '';
        $posted_csrf = $_POST['csrf_token'] ?? '';

        $_SESSION['last_login_attempt'] = time();

        // Validate CSRF token
        if (!hash_equals($csrf_token, $posted_csrf)) {
            throw new RuntimeException("Invalid session. Please refresh the page and try again.");
        }

        // Validate inputs
        if (empty($employee_number) || empty($plain_password)) {
            throw new RuntimeException("Please fill in all fields.");
        }

        // Validate employee number format
        if (!preg_match('/^EMP\d{5}$/', $employee_number)) {
            usleep(500000); // Delay for invalid format
            throw new RuntimeException("Invalid employee number or password.");
        }

        // Database connection check
        if (!$conn) {
            error_log('[employee_login] Database connection failed: ' . mysqli_connect_error());
            throw new RuntimeException('Service temporarily unavailable. Please try again later.');
        }

        // Query employee information
        $stmt = $conn->prepare("
            SELECT 
                employee_id, 
                employee_number, 
                first_name, 
                middle_name, 
                last_name, 
                password, 
                status,
                role_id
            FROM employees 
            WHERE employee_number = ? 
            LIMIT 1
        ");
        if (!$stmt) {
            error_log('[employee_login] Prepare failed: ' . $conn->error);
            throw new RuntimeException('Service temporarily unavailable. Please try again later.');
        }

        $stmt->bind_param("s", $employee_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Check if account is active
            if (isset($row['status']) && strtolower($row['status']) !== 'active') {
                $_SESSION[$rate_limit_key]++;
                throw new RuntimeException('Account is inactive. Please contact administrator.');
            }

            // Verify password
            $isPasswordValid = false;
            if (preg_match('/^\$2[aby]\$/', $row['password']) || preg_match('/^\$argon2/', $row['password'])) {
                $isPasswordValid = password_verify($plain_password, $row['password']);
            } else {
                error_log('[employee_login] Invalid password format for user: ' . $employee_number);
                $_SESSION[$rate_limit_key]++;
                throw new RuntimeException("Account configuration error. Please contact administrator.");
            }

            if ($isPasswordValid) {
                // Check if role_id exists
                if (empty($row['role_id'])) {
                    error_log('[employee_login] No role_id found for user: ' . $employee_number);
                    throw new RuntimeException("Role not configured. Please contact administrator.");
                }

                // Map role_id to role name (adjust these mappings based on your actual role_id values)
                $roleMap = [
                    1 => 'admin',
                    2 => 'doctor', 
                    3 => 'nurse',
                    4 => 'pharmacist',
                    5 => 'bhw',
                    6 => 'dho',
                    7 => 'records officer',
                    8 => 'cashier',
                    9 => 'laboratory tech'
                ];
                
                $role = $roleMap[$row['role_id']] ?? 'admin'; // Default to admin if role_id not found

                // Successful login - reset rate limit
                unset($_SESSION[$rate_limit_key], $_SESSION['last_login_attempt']);
                
                // Prevent session fixation
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['employee_id'] = $row['employee_id'];
                $_SESSION['employee_number'] = $row['employee_number'];
                $_SESSION['employee_last_name'] = $row['last_name'];
                $_SESSION['employee_first_name'] = $row['first_name'];
                $_SESSION['employee_middle_name'] = $row['middle_name'];
                
                // Create full name for dashboard compatibility
                $full_name = $row['first_name'];
                if (!empty($row['middle_name'])) $full_name .= ' ' . $row['middle_name'];
                $full_name .= ' ' . $row['last_name'];
                $_SESSION['employee_name'] = trim($full_name);
                
                $_SESSION['role'] = $role;
                $_SESSION['role_id'] = $row['role_id'];
                $_SESSION['login_time'] = time();
                $_SESSION['user_type'] = $role; // For compatibility
                $_SESSION['user_id'] = $row['employee_id']; // For admin dashboard compatibility

                // Role-based dashboard redirection
                $dashboardMap = [
                    'admin' => '../admin/dashboard.php',
                    'administrator' => '../admin/dashboard.php',
                    'doctor' => '../doctor/dashboard.php',
                    'nurse' => '../nurse/dashboard.php',
                    'pharmacist' => '../pharmacist/dashboard.php',
                    'bhw' => '../bhw/dashboard.php',
                    'barangay health worker' => '../bhw/dashboard.php',
                    'dho' => '../dho/dashboard.php',
                    'district health officer' => '../dho/dashboard.php',
                    'records officer' => '../records_officer/dashboard.php',
                    'cashier' => '../cashier/dashboard.php',
                    'laboratory tech' => '../laboratory_tech/dashboard.php',
                    'laboratory technician' => '../laboratory_tech/dashboard.php',
                    'lab tech' => '../laboratory_tech/dashboard.php'
                ];
                
                if (array_key_exists($role, $dashboardMap)) {
                    header('Location: ' . $dashboardMap[$role]);
                    exit();
                } else {
                    error_log('[employee_login] Unknown role: ' . $role . ' (role_id: ' . $row['role_id'] . ') for user: ' . $employee_number);
                    // Default to admin dashboard for unrecognized roles but log it
                    header('Location: ../admin/dashboard.php');
                    exit();
                }
            } else {
                $_SESSION[$rate_limit_key]++;
                usleep(500000); // Delay for failed authentication
                throw new RuntimeException("Invalid employee number or password.");
            }
        } else {
            $_SESSION[$rate_limit_key]++;
            usleep(500000); // Delay for non-existent user
            throw new RuntimeException("Invalid employee number or password.");
        }
        
        $stmt->close();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        error_log('[employee_login] Unexpected error: ' . $e->getMessage());
        $error = "Service temporarily unavailable. Please try again later.";
    }
}

// Handle flash messages
$sessionFlash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flash = $sessionFlash ?: (!empty($error) ? ['type' => 'error', 'msg' => $error] : null);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, maximum-scale=5.0" />
    <meta name="theme-color" content="#2563eb">
    <title>CHO – Employee Login</title>
    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../../../assets/css/login.css" />
    <style>
        /* Enhanced Snackbar */
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

        /* Subtle inline help under inputs */
        .input-help {
            display: block;
            margin-top: 4px;
            font-size: 0.85rem;
            color: #6b7280;
            line-height: 1.3;
        }

        /* Login attempts warning */
        .attempts-warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
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
            <h1 id="login-title" class="visually-hidden">Employee Login</h1>
            
            <!-- Show attempts warning if applicable -->
            <?php if ($_SESSION[$rate_limit_key] >= 3 && $_SESSION[$rate_limit_key] < $max_attempts): ?>
            <div class="attempts-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Warning: <?= $_SESSION[$rate_limit_key] ?> failed attempts. Account will be temporarily locked after <?= $max_attempts ?> attempts.
            </div>
            <?php endif; ?>
            
            <form class="form active" action="employee_login.php" method="POST" autocomplete="off" novalidate>
                <div class="form-header">
                    <h2>Employee Login</h2>
                </div>

                <!-- Employee Number -->
                <label for="employee_number">Employee Number</label>
                <input type="text" id="employee_number" name="employee_number" class="input-field"
                    placeholder="Enter Employee Number (e.g., EMP00001)" inputmode="text" autocomplete="username"
                    pattern="^EMP\d{5}$" aria-describedby="employee-number-help" required
                    value="<?= htmlspecialchars($employee_number) ?>" />
                <span class="input-help" id="employee-number-help">Format: EMP followed by 5 digits (e.g., EMP00001).</span>
                
                <!-- Password -->
                <div class="password-wrapper">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input-field"
                        placeholder="Enter Password" autocomplete="current-password" required />
                    <button type="button" class="toggle-password" aria-label="Show password" aria-pressed="false"
                        title="Show/Hide Password" tabindex="0">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- CSRF token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>" />

                <div class="form-footer">
                    <a href="employee_forgot_password.php" class="forgot">Forgot Password?</a>
                </div>

                <button type="submit" class="btn">Login</button>

                <p class="alt-action">
                    Patient Login? 
                    <a class="register-link" href="../../auth/patient_login.php">Go to Patient Portal</a>
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

        // Enhanced client validation
        (function() {
            const form = document.querySelector("form");
            const status = document.getElementById("form-status");
            const empNumInput = document.getElementById("employee_number");
            
            if (!form || !status) return;

            // Real-time validation for employee number
            empNumInput?.addEventListener('input', function() {
                const value = this.value.toUpperCase();
                if (value !== this.value) {
                    this.value = value;
                }
            });

            form.addEventListener("submit", function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    status.textContent = "Please fix the highlighted fields.";
                }
            });
        })();

        // Enhanced snackbar with animation reset
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
            setTimeout(() => el.classList.remove('show'), 5000);
        })();

        // Security: Clear form on page unload
        window.addEventListener('beforeunload', function() {
            const form = document.querySelector('form');
            if (form) form.reset();
        });
    </script>
</body>
</html>