<?php
// Main entry point for the website
// At the VERY TOP of your PHP file (before session_start or other code)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax'); // or 'Strict' if flows allow
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
error_reporting(E_ALL); // log everything, just don't display in prod
session_start();

include_once __DIR__ . '/../../config/db.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['patient_id'])) {
    header('Location: /wbhsms-cho-koronadal/pages/dashboard/dashboard_patient.php');
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

// Handle POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_number = strtoupper(trim($_POST['username'] ?? '')); // normalize to uppercase
    $patient_number = preg_replace('/\s+/', '', $patient_number); // remove spaces just in case
    $password = $_POST['password'] ?? '';

    if ($patient_number === '' || $password === '') {
        $error = 'Please enter both Patient Number and Password.';
    } elseif (!preg_match('/^P\d{6}\z/', $patient_number)) {
        usleep(300000);
        $error = 'Invalid Patient Number or Password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password FROM patients WHERE username = ?');
        $stmt->execute([$patient_number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['patient_id'] = $row['id'];
            header('Location: /wbhsms-cho-koronadal/pages/dashboard/dashboard_patient.php');
            exit;
        } else {
            usleep(300000);
            $error = 'Invalid Patient Number or Password.';
        }
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
            <h1 id="login-title" class="visually-hidden">Patient Login</h1>

            <form class="form active" action="patient_login.php" method="POST" novalidate>
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