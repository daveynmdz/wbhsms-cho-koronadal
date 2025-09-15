<?php

declare(strict_types=1);

// Prevent "headers already sent"
ob_start();
session_start();

ini_set('display_errors', '1');                // turn off in production
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

include_once __DIR__ . '/../../config/db.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const OTP_PAGE = '/pages/auth/forgot_password_otp.php?reset=1';

/** Helpers */
function generateOTP(int $length = 6): string
{
    return str_pad((string)random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}
function isPatientId(string $s): bool
{
    return (bool)preg_match('/^[Pp]\d{6}$/', $s);
}
function isEmail(string $s): bool
{
    return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1) Read & validate
        $identifier = isset($_POST['identifier']) ? trim((string)$_POST['identifier']) : '';
        if ($identifier === '') {
            throw new RuntimeException('Please enter your Patient ID (e.g., P123456) or your registered email.');
        }
        $isPid   = isPatientId($identifier);
        $isEmail = isEmail($identifier);
        if (!$isPid && !$isEmail) {
            throw new RuntimeException('Invalid format. Enter Patient ID like P123456, or a valid email address.');
        }

        // 2) Lookup (make sure $pdo exists in db.php)
        if ($isPid) {
            // Case-insensitive compare for username/patient id
            error_log('[forgot_password] Searching by PID: ' . $identifier);
            $stmt = $pdo->prepare("
                SELECT id, email, first_name, last_name
                FROM patients
                WHERE UPPER(TRIM(username)) = UPPER(TRIM(?))
                LIMIT 1
            ");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Case-insensitive compare for email (primary)
            error_log('[forgot_password] Searching by EMAIL: ' . $identifier);
            $stmt = $pdo->prepare("
                SELECT id, email, first_name, last_name
                FROM patients
                WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fallback: some rows may store the email in username
            if (!$user) {
                error_log('[forgot_password] No row in email column. Trying username=email fallback…');
                $stmt = $pdo->prepare("
                    SELECT id, email, first_name, last_name
                    FROM patients
                    WHERE LOWER(TRIM(username)) = LOWER(TRIM(?))
                    LIMIT 1
                ");
                $stmt->execute([$identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if (!$user) {
            throw new RuntimeException('No matching user found.');
        }

        // 3) Store OTP in session, then close session so next page can read it
        $otp = generateOTP();
        $_SESSION['reset_otp']      = $otp;
        $_SESSION['reset_user_id']  = $user['id'];
        $_SESSION['reset_email']    = $user['email'];
        $_SESSION['reset_name']     = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $_SESSION['reset_otp_time'] = time(); // optional: enforce expiry on the OTP page
        // ✅ Flash message for next page
        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => 'OTP sent to ' . $user['email'] . '. Check your inbox (or spam) and enter the code below.'
        ];

        session_write_close(); // important

        // 4) Send mail (best effort). Failures don’t block redirect.
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?? 'cityhealthofficeofkoronadal@gmail.com';
            $mail->Password   = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?? 587);

            $mail->setFrom('cityhealthofficeofkoronadal@gmail.com', 'City Health Office of Koronadal');
            $mail->addAddress($user['email'], $_SESSION['reset_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for Password Reset';
            $mail->Body    = "<p>Your One-Time Password (OTP) is: <strong>{$otp}</strong></p>";

            $mail->send();
            error_log('[forgot_password] Mail sent to ' . $user['email']);
        } catch (Exception $e) {
            error_log('[forgot_password] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
        }

        // 5) Clean any accidental output to ensure headers work
        if (headers_sent($hsFile, $hsLine)) {
            error_log("Headers already sent at $hsFile:$hsLine — attempting to continue.");
        }
        if (ob_get_length()) {
            ob_end_clean();
        }
        // 6) Redirect to OTP page (303 = See Other)
        header('Location: ' . OTP_PAGE, true, 303);
        header('X-Debug-Redirect: OTP', replace: true);
        exit;
    } catch (Throwable $e) {
        error_log('[forgot_password] Fatal: ' . $e->getMessage());
        $error = $e->getMessage(); // show in UI
    }
}
//after successful UPDATE, redirect to login


// === If GET, or POST with $error, render the form ===
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

        .form-footer {
            margin-top: 10px;
        }

        /* Keep the rest of your styling; this is just the error box */
        .error {
            display: block;
            margin: .6rem 0 18px;
            padding: .65rem .75rem;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
        }

        .error[hidden] {
            display: none
        }

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
                transform: rotate(360deg);
            }
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

    <section class="homepage" style="min-height:100vh; display:grid; place-items:start center; padding:160px 16px 40px;">
        <div class="registration-box" style="max-width:400px; width:100%;">
            <h2 style="margin:0 0 20px 0;">Forgot Password</h2>

            <div id="error-message" class="error" <?= $error ? '' : 'hidden' ?>>
                <?= htmlspecialchars($error ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>

            <!-- NORMAL POST submit (no JS fetch) -->
            <form id="forgotForm"
                method="POST"
                action="/pages/auth/forgot_password.php"
                autocomplete="off" novalidate>

                <label for="identifier">Enter your Patient ID or Email Address</label>
                <input type="text" id="identifier" name="identifier" class="input-field" required
                    placeholder="Patient ID (e.g., P123456) or your email" />

                <div class="reminder" style="font-size: smaller; color:#555; margin:8px 0 16px 0; text-align:justify;">
                    <strong>Reminder:</strong>
                    <i>Please type your registered email or your respective Patient ID (e.g. <b>P000001</b>).
                        If you forgot your email or Patient ID, please contact your administrator or your local health worker for assistance.</i>
                </div>

                <div class="form-footer" style="text-align:center; margin-top:18px;">
                    <a href="/pages/auth/patient_login.php"
                        class="btn" style="background:#eee; color:#333; padding:8px 24px; border-radius:10px; text-decoration:none; display:inline-block; margin-right:8px;">
                        Back to Login
                    </a>
                    <button id="submitBtn" type="submit" class="btn" style="padding:8px 24px; border-radius:10px;">
                        Send OTP
                    </button>
                </div>
            </form>
        </div>
    </section>
    <script>
        (function() {
            const form = document.getElementById('forgotForm');
            const btn = document.getElementById('submitBtn');
            if (!form || !btn) return;

            form.addEventListener('submit', function() {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                // lock width to prevent jump
                const w = btn.getBoundingClientRect().width;
                btn.style.width = w + 'px';
                btn.innerHTML = '<span class="spinner" aria-hidden="true"></span>Sending…';
            }, {
                once: true
            });
        })();
    </script>
</body>

</html>