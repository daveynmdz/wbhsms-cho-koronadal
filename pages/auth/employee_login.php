<?php
// Main entry point for the website
// At the VERY TOP of your PHP file (before session_start or other code)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax'); // or 'Strict' if flows allow
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

session_start();

include_once __DIR__ . '/../../config/db.php';

// CSRF token generation and validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Simple login rate limiting (per session/IP)
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['last_login_attempt'])) $_SESSION['last_login_attempt'] = 0;
$max_attempts = 10;
$block_seconds = 60; // Block for 1 minute after max attempts

$error = '';
$employee_number = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting
    if ($_SESSION['login_attempts'] >= $max_attempts && (time() - $_SESSION['last_login_attempt']) < $block_seconds) {
        $error = "Too many login attempts. Please wait a minute before trying again.";
    } else {
        $employee_number = trim($_POST['employee_number'] ?? '');
        $plain_password = $_POST['password'] ?? '';
        $posted_csrf = $_POST['csrf_token'] ?? '';

        $_SESSION['last_login_attempt'] = time();

        // Validate CSRF token
        if (!hash_equals($csrf_token, $posted_csrf)) {
            $error = "Invalid session. Please refresh the page and try again.";
        }
        // Validate employee number format
        elseif (!preg_match('/^EMP\d{5}$/', $employee_number)) {
            $error = "Invalid employee number format.";
        }
        elseif ($employee_number && $plain_password) {
            // Use the $conn object from db.php (MySQLi connection)
            if (!$conn) {
                die('Database connection failed: ' . mysqli_connect_error());
            }
            $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_number = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $employee_number);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    // Refined: Always use password_verify for hashed passwords
                    $isPasswordValid = false;
                    // Accept bcrypt ($2y$, $2a$, $2b$) and argon2 hashes
                    if (preg_match('/^\$2[aby]\$/', $row['password']) || preg_match('/^\$argon2/', $row['password'])) {
                        $isPasswordValid = password_verify($plain_password, $row['password']);
                    } else {
                        // If not a recognized hash, treat as invalid
                        $error = "Password format error. Contact admin.";
                    }

                    if ($isPasswordValid) {
                        // Prevent session fixation
                        session_regenerate_id(true);

                        // Reset rate limit on successful login
                        $_SESSION['login_attempts'] = 0;

                        // Set session variables
                        $_SESSION['employee_id'] = $row['employee_id'];
                        $_SESSION['employee_number'] = $row['employee_number'];
                        $_SESSION['employee_last_name'] = $row['last_name'];
                        $_SESSION['employee_first_name'] = $row['first_name'];
                        $_SESSION['employee_middle_name'] = $row['middle_name'];
                        $_SESSION['role'] = $row['role'];

                        // Role-based dashboard redirection
                        $role = strtolower(trim($row['role']));
                        $dashboardMap = [
                            'admin' => '../dashboard/dashboard_admin.php',
                            'doctor' => '../dashboard/dashboard_doctor.php',
                            'nurse' => '../dashboard/dashboard_nurse.php',
                            'pharmacist' => '../dashboard/dashboard_pharmacist.php',
                            'bhw' => '../dashboard/dashboard_bhw.php',
                            'dho' => '../dashboard/dashboard_dho.php',
                            'records officer' => '../dashboard/dashboard_records_officer.php',
                            'cashier' => '../dashboard/dashboard_cashier.php',
                            'laboratory tech.' => '../dashboard/dashboard_laboratory_tech.php'
                        ];
                        if (array_key_exists($role, $dashboardMap)) {
                            header('Location: ' . $dashboardMap[$role]);
                            exit();
                        } else {
                            $error = "Unknown role. Please contact admin.";
                        }
                    } else if (empty($error)) {
                        $error = "Invalid employee number or password.";
                        $_SESSION['login_attempts']++;
                    }
                } else {
                    $error = "Invalid employee number or password.";
                    $_SESSION['login_attempts']++;
                }
                $stmt->close();
            } else {
                $error = "Database error: " . $conn->error;
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CHO – Employee Login</title>
    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../../assets/css/login.css" />
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
            <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128"
                alt="City Health Office Koronadal logo" width="100" height="100" decoding="async" />
        </div>
    </header>
    <main class="homepage" id="main-content">
        <section class="login-box" aria-labelledby="login-title">
            <h1 id="login-title" class="visually-hidden">Employee Login</h1>
            <form class="form active" action="employee_login.php" method="POST" autocomplete="off">
                <div class="form-header">
                    <h2>Employee Login</h2>
                </div>

                <!-- Snackbar for error messages -->
                <div id="snackbar" class="<?= !empty($error) ? 'error show' : '' ?>">
                    <?= !empty($error) ? htmlspecialchars($error) : '' ?>
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
                    <a href="employeeForgotPassword.php" class="forgot">Forgot Password?</a>
                </div>

                <button type="submit" class="btn">Login</button>

                <!-- Live region for client-side validation or server messages -->
                <div class="sr-only" role="status" aria-live="polite" id="form-status"></div>
            </form>
        </section>
    </main>
    <script>
        // Password toggle (accessible, no validation logic)
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.querySelector(".toggle-password");
            const pwd = document.getElementById("password");
            const icon = toggleBtn.querySelector("i");
            toggleBtn.addEventListener("click", function() {
                const isHidden = pwd.type === "password";
                pwd.type = isHidden ? "text" : "password";
                toggleBtn.setAttribute("aria-pressed", String(isHidden));
                toggleBtn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
                icon.classList.toggle("fa-eye");
                icon.classList.toggle("fa-eye-slash");
            });

            // Show snackbar for error messages
            const snackbar = document.getElementById('snackbar');
            if (snackbar && snackbar.classList.contains('show')) {
                setTimeout(function() {
                    snackbar.classList.remove('show');
                }, 3000);
            }
        });
    </script>
</body>
</html>