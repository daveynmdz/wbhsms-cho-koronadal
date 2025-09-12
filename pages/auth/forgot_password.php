<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/../../config/db.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** Generate a 6-digit OTP (zero-padded) */
function generateOTP($length = 6)
{
    return str_pad((string)random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

/** Patient ID: P/p + 6 digits (e.g., P123456) */
function isPatientId(string $s): bool {
    return preg_match('/^[Pp]\d{6}$/', $s) === 1;
}

/** Email must contain @ and end with .com (simple rule as requested) */
function isComEmail(string $s): bool {
    return preg_match('/^[^\s@]+@[^\s@]+\.com$/i', $s) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';

        // Empty input
        if ($identifier === '') {
            echo json_encode(['success' => false, 'message' => 'Please enter your Patient ID (P123456) or email (name@example.com).']);
            exit;
        }

        // Syntax validation
        $isPid   = isPatientId($identifier);
        $isEmail = isComEmail($identifier);

        if (!$isPid && !$isEmail) {
            echo json_encode(['success' => false, 'message' => 'Invalid format. Use Patient ID like P123456 or an email ending in .com.']);
            exit;
        }

        // Lookup based on valid syntax
        if ($isPid) {
            // Normalize to uppercase 'P' to avoid case quirks
            $identifierNorm = 'P' . substr($identifier, 1);
            $stmt = $pdo->prepare("
                SELECT id, email, first_name, last_name
                FROM patients
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->execute([$identifierNorm]);
        } else { // $isEmail
            $stmt = $pdo->prepare("
                SELECT id, email, first_name, last_name
                FROM patients
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$identifier]);
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'No matching user found.']);
            exit;
        }

        // Create OTP and store in session
    $otp = generateOTP();
    $_SESSION['reset_otp']     = $otp;
    $_SESSION['reset_otp_time'] = time(); // Store OTP creation time
    $_SESSION['reset_user_id'] = $user['id'];
    $_SESSION['reset_email']   = $user['email'];
    $_SESSION['reset_name']    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        // Send OTP via email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'cityhealthofficeofkoronadal@gmail.com';
            $mail->Password   = 'iclhoflunfkzmlie';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('cityhealthofficeofkoronadal@gmail.com', 'City Health Office of Koronadal');
            $mail->addAddress($user['email'], trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));

            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for Password Reset';
            $mail->Body    = "<p>Your One-Time Password (OTP) for password reset is: <strong>{$otp}</strong></p>";

            $mail->send();
            error_log('[forgot_password] Mail sent to ' . $user['email']);
            echo json_encode(['success' => true]);
            exit;

        } catch (Exception $e) { // PHPMailer\Exception (aliased above)
            error_log('[forgot_password] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            echo json_encode(['success' => false, 'message' => 'OTP could not be sent. Please try again later.']);
            exit;
        }

    } catch (\Throwable $e) {
        error_log('[forgot_password] Fatal: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
        exit;
    }

} else {
    // Only show HTML if not an AJAX POST request
    // (Keep your existing HTML below this line)
?>

c

    <body>
        <header>
            <div class="logo-container">
                <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128"
                    alt="CHO Koronadal Logo" />
            </div>
        </header>
        <section class="homepage">
            <div class="registration-box">
                <h2 style="margin:0 0 20px 0;">Forgot Password</h2>
                <div id="error-message" class="error"></div>
                <form id="forgotForm" class="form" autocomplete="off" novalidate>
                    <label for="identifier">Enter your Patient ID or Email Address</label>
                    <input type="text" id="identifier" name="identifier" class="input-field" required
                        placeholder="Patient ID or Email" />
                    <div class="reminder" style="font-size:0.98em; color:#555; margin:8px 0 16px 0;text-align: justify;font-size: smaller;">
                        <strong>Reminder:</strong> <i>Please type your registered email or your respective Patient ID (e.g. <b>P000001</b>).<br>
                            If you forgot your email or Patient ID, please contact your administrator or your local health worker for assistance.</i>
                    </div>
                    <div class="form-footer" style="display: block;text-align:center; margin-top:18px;">
                        <button id="backBtn" type="button" class="btn"
                            style="background:#eee; color:#333; padding:8px 24px; border-radius:4px; text-decoration:none; border:none; display:inline-block; margin-right:8px;">Back
                            to Login</button>
                        <button id="submitBtn" type="submit" class="btn" form="forgotForm"
                            style="background:#007bff; color:#fff; padding:8px 24px; border-radius:4px; text-decoration:none; border:none; display:inline-block;">Send
                            OTP <i class="fa-solid fa-arrow-right"></i></button>
                </form>
            </div>
        </section>
        <script>
            const backBtn = document.getElementById('backBtn');
            backBtn.addEventListener('click', function() {
                window.location.href = 'patient_login.php';
            });
            const form = document.getElementById('forgotForm');
            const error = document.getElementById('error');
            const submitBtn = document.getElementById('submitBtn');

            function showError(msg) {
                error.textContent = msg;
                error.style.display = 'block';
                setTimeout(() => {
                    error.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 50);
            }

            function clearError() {
                error.textContent = '';
                error.style.display = 'none';
            }
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                clearError();
                const identifier = document.getElementById('identifier').value.trim();
                const errorMessage = document.getElementById('error-message');

                // Regex patterns
                const patientIdPattern = /^[Pp]\d{6}$/; // P/p + 6 digits
                const emailPattern = /^[^\s@]+@[^\s@]+\.com$/i; // simple email check for @ and .com

                // Validation check
                if (!patientIdPattern.test(identifier) && !emailPattern.test(identifier)) {
                    errorMessage.textContent = 'Please enter a valid Patient ID (P123456) or Email (name@example.com).';
                    errorMessage.style.display = 'block';
                    return; // Stop here if invalid
                } else {
                    errorMessage.style.display = 'none';
                }

                // Disable button while sending
                submitBtn.disabled = true;

                // Continue with your fetch as before
                fetch('./forgot_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'identifier=' + encodeURIComponent(identifier)
                    })
                    .then(async (res) => {
                        let data;
                        try {
                            data = await res.json();
                        } catch {
                            throw new Error('Invalid server response');
                        }
                        if (!res.ok) throw new Error(data?.message || 'Request failed');
                        return data;
                    })
                    .then((data) => {
                        if (data.success) {
                            window.location.href = 'forgot_password_otp.php?reset=1';
                        } else {
                            errorMessage.textContent = data.message || 'No matching user found.';
                            errorMessage.style.display = 'block';
                            submitBtn.disabled = false;
                        }
                    })
                    .catch((err) => {
                        errorMessage.textContent = err.message || 'Server error. Please try again.';
                        errorMessage.style.display = 'block';
                        submitBtn.disabled = false;
                    });
            });
        </script>
    </body>

    </html><?php
        }
