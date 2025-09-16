<?php
// Main entry point for the website
// At the VERY TOP of your PHP file (before session_start or other code)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL); // log everything, just don't display in prod
session_start();

include_once __DIR__ . '/../../config/db.php';

$sessionFlash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']); // one-time
$flash = $sessionFlash ?: (!empty($error) ? ['type' => 'error', 'msg' => $error] : null);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_number = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($patient_number) || empty($password)) {
        $error = 'Please enter both Patient Number and Password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password FROM patients WHERE username = ?');
        $stmt->execute([$patient_number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['patient_id'] = $row['id'];
            header('Location: /WBHSMS-CHO/pages/dashboard/dashboard_patient.php');
            exit();
        } else {
            $error = 'Invalid Patient Number or Password.';
        }
    }
}

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

            <form class="form active" action="patientLogin.php" method="POST" novalidate>
                <div class="form-header">
                    <h2>Patient Login</h2>
                </div>

                <!-- Patient Number -->
                <label for="username">Patient Number</label>
                <input type="text" id="username" name="username" class="input-field"
                    placeholder="Enter Patient Number (e.g., P000001)" inputmode="text" autocomplete="username" pattern="^P\d{6}$"
                    aria-describedby="username-help" required />
                <!-- Password -->
                <div class="password-wrapper">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input-field" placeholder="Enter Password"
                        autocomplete="current-password" required />
                    <button type="button" class="toggle-password" aria-label="Show password" aria-pressed="false"
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