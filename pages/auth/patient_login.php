<?php
session_start();
require_once '../../../config/db.php';

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
            header('Location: /WBHSMS-CHO/public/patient/patientHomepage.php');
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
                    <a href="forgotPassword.html" class="forgot">Forgot Password?</a>
                </div>

                <button type="submit" class="btn">Login</button>

                <p class="alt-action">
                    Don’t have an account?
                    <a class="register-link" href="patientRegistration.php">Register</a>
                </p>

                <!-- Live region for client-side validation or server messages -->
                <div class="sr-only" role="status" aria-live="polite" id="form-status"></div>
            </form>
        </section>
    </main>

    <script>
        // Password toggle (accessible)
        (function() {
            const toggleBtn = document.querySelector(".toggle-password");
            const pwd = document.getElementById("password");
            const icon = toggleBtn.querySelector("i");

            function toggle() {
                const isHidden = pwd.type === "password";
                pwd.type = isHidden ? "text" : "password";
                toggleBtn.setAttribute("aria-pressed", String(isHidden));
                toggleBtn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
                icon.classList.toggle("fa-eye");
                icon.classList.toggle("fa-eye-slash");
            }

            toggleBtn.addEventListener("click", toggle);
        })();

        // Optional: Light client validation message surface
        (function() {
            const form = document.querySelector("form");
            const status = document.getElementById("form-status");
            form.addEventListener("submit", function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    status.textContent = "Please fix the highlighted fields.";
                }
            });
        })();
    </script>
</body>

</html>