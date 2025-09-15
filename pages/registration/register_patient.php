<?php

declare(strict_types=1);
// register_patient_hardened_fixed.php
// Hardened backend for patient registration with OTP email + redirects.
//
// Key improvements:
// - Proper try/catch structure (no stray try without catch)
// - Defines back_with_error() helper
// - Validates inputs & checks duplicates
// - Hashes password (stored in session; do NOT keep plaintext)
// - Generates OTP and stores expiry in session
// - Sends OTP using PHPMailer with clear error handling
// - Redirects to registration_verify.php on success, back to patient_registration.php on error
// Put this at the very top of PHP files that do redirects (before session_start)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// ---- Session hardening ----
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443);

ini_set('session.cookie_secure', $https ? '1' : '0'); // secure only when HTTPS
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_regenerate_id(true);
require_once '../../config/db.php'; // must define $pdo (PDO)

// ---- Load PHPMailer (prefer Composer, fallback to manual includes) ----
if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        require_once '../../phpmailer/phpmailer/src/PHPMailer.php';
        require_once '../../phpmailer/phpmailer/src/SMTP.php';
        require_once '../../phpmailer/phpmailer/src/Exception.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---- Configurable paths ----
$otp_page    = 'registration_otp.php';
$return_page = 'patient_registration.php';

// ---- Helper: redirect back with an error message ----
function back_with_error(string $msg, int $http_code = 303): void
{
    $_SESSION['registration_error'] = $msg;
    http_response_code($http_code);
    global $return_page;
    header('Location: ' . $return_page, true, $http_code);
    exit;
}

// ---- Make PDO throw exceptions (optional but helpful) ----
if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- CSRF ---
        if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
            back_with_error('Invalid or missing CSRF token.');
        }
        // Rotate token after successful check
        unset($_SESSION['csrf_token']);

        // --- Collect fields ---
        $first_name  = isset($_POST['first_name']) ? trim((string)$_POST['first_name']) : '';
        $last_name   = isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '';
        $middle_name = isset($_POST['middle_name']) ? trim((string)$_POST['middle_name']) : '';
        $suffix      = isset($_POST['suffix']) ? trim((string)$_POST['suffix']) : '';
        $dob         = isset($_POST['dob']) ? trim((string)$_POST['dob']) : '';
        $email       = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
        $contact_num = isset($_POST['contact_num']) ? trim((string)$_POST['contact_num']) : '';
        $barangay    = isset($_POST['barangay']) ? trim((string)$_POST['barangay']) : '';
        $password    = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $sex         = isset($_POST['sex']) ? trim((string)$_POST['sex']) : '';
        $agree_terms = isset($_POST['agree_terms']); // checkbox
        $email       = strtolower($email);

        // Pre-stash for repopulation on error (no passwords)
        $_SESSION['registration'] = [
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'middle_name' => $middle_name,
            'suffix'      => $suffix,
            'barangay'    => $barangay,
            'dob'         => $dob,
            'sex'         => $sex,
            'contact_num' => $contact_num, // keep as typed for UI; we store normalized later on success
            'email'       => $email,
        ];


        // --- Required fields ---
        if (
            $first_name === '' || $last_name === '' || $dob === '' || $email === '' ||
            $contact_num === '' || $barangay === '' || $password === '' || $sex === ''
        ) {
            back_with_error('All fields are required.');
        }
        if (!$agree_terms) {
            back_with_error('You must agree to the Terms & Conditions.');
        }
        if (!in_array($sex, ['Male', 'Female'], true)) {
            back_with_error('Please select a valid sex.');
        }

        // --- Email ---
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            back_with_error('Please enter a valid email address.');
        }

        // --- Barangay whitelist ---
        $allowed_barangays = [
            'Brgy. Assumption',
            'Brgy. Avanceña',
            'Brgy. Cacub',
            'Brgy. Caloocan',
            'Brgy. Carpenter Hill',
            'Brgy. Concepcion',
            'Brgy. Esperanza',
            'Brgy. General Paulino Santos',
            'Brgy. Mabini',
            'Brgy. Magsaysay',
            'Brgy. Mambucal',
            'Brgy. Morales',
            'Brgy. Namnama',
            'Brgy. New Pangasinan',
            'Brgy. Paraiso',
            'Brgy. Rotonda',
            'Brgy. San Isidro',
            'Brgy. San Roque',
            'Brgy. San Jose',
            'Brgy. Sta. Cruz',
            'Brgy. Sto. Niño',
            'Brgy. Saravia',
            'Brgy. Topland',
            'Brgy. Zone 1',
            'Brgy. Zone 2',
            'Brgy. Zone 3',
            'Brgy. Zone 4'
        ];
        if (!in_array($barangay, $allowed_barangays, true)) {
            back_with_error('Please select a valid barangay.');
        }

        // --- Phone normalize (PH mobile: 9xxxxxxxxx, 09xxxxxxxxx, or +639xxxxxxxxx) ---
        $digits = preg_replace('/\D+/', '', $contact_num);
        if (preg_match('/^639\d{9}$/', $digits)) {
            $normalizedContactNum = substr($digits, 2); // 9xxxxxxxxx
        } elseif (preg_match('/^09\d{9}$/', $digits)) {
            $normalizedContactNum = substr($digits, 1); // 9xxxxxxxxx
        } elseif (preg_match('/^9\d{9}$/', $digits)) {
            $normalizedContactNum = $digits;
        } else {
            back_with_error('Contact number must be a valid PH mobile (e.g., 9xxxxxxxxx or +639xxxxxxxxx).');
        }

        // --- DOB format YYYY-MM-DD and not future ---
        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
        $dobErrors = DateTime::getLastErrors();
        if (
            !$dobDate ||
            !is_array($dobErrors) ||
            ($dobErrors['warning_count'] ?? 0) > 0 ||
            ($dobErrors['error_count'] ?? 0) > 0
        ) {
            back_with_error('Date of birth must be in YYYY-MM-DD format.');
        }
        $today = new DateTime('today');
        if ($dobDate > $today) {
            back_with_error('Date of birth cannot be in the future.');
        }

        // ADD:
        $oldest = (clone $today)->modify('-120 years');
        if ($dobDate < $oldest) {
            back_with_error('Please enter a valid date of birth.');
        }


        // --- Password policy (len + upper + lower + digit) ---
        if (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/\d/', $password)
        ) {
            back_with_error('Password must be at least 8 characters with uppercase, lowercase, and a number.');
        }


        // --- Duplicate check ---
        $count = 0;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE first_name = ? AND last_name = ? AND dob = ? AND barangay = ?');
            if ($stmt && $stmt->execute([$first_name, $last_name, $dob, $barangay])) {
                $result = $stmt->fetchColumn();
                if ($result !== false) {
                    $count = (int)$result;
                }
            }
        } catch (Throwable $e) {
            error_log('Duplicate check error: ' . $e->getMessage());
            // Do not output anything to browser
        }
        if ($count > 0) {
            back_with_error('Patient already exists.');
        }

        // --- Hash password ---
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // --- OTP Generation (6 digits) ---
        $otp         = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_expiry  = time() + 300; // 5 minutes

        // --- Store registration data & OTP in session (NO plaintext password) ---
        $_SESSION['registration'] = [
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'middle_name'  => isset($_POST['middle_name']) ? trim((string)$_POST['middle_name']) : '',
            'suffix'       => isset($_POST['suffix']) ? trim((string)$_POST['suffix']) : '',
            'barangay'     => $barangay,
            'dob'          => isset($_POST['dob']) ? trim((string)$_POST['dob']) : '',
            'sex'          => $sex,
            'contact_num'  => $normalizedContactNum, // <- use normalized digits
            'email'        => $email,                // already lowercased
            'password'     => $hashed // store hashed only
        ];
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = $otp_expiry;

        // --- Send OTP via PHPMailer ---
        $mail = new PHPMailer(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        try {
            // Load SMTP config from environment variables
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username   = $_ENV['SMTP_USER'] ?? 'cityhealthofficeofkoronadal@gmail.com';
            $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
            $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;
            $fromEmail        = $_ENV['SMTP_FROM'] ?? 'cityhealthofficeofkoronadal@gmail.com';
            $fromName         = $_ENV['SMTP_FROM_NAME'] ?? 'City Health Office of Koronadal';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email, $first_name . ' ' . $last_name);
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for CHO Koronadal Registration';
            $mail->Body    = '<p>Your One-Time Password (OTP) for registration is:</p><h2 style="letter-spacing:2px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</h2><p>This code will expire in 5 minutes.</p>';
            $mail->AltBody = "Your OTP is: {$otp} (expires in 5 minutes)";

            $mail->send();

            // Success → redirect to OTP page
            header('Location: ' . $otp_page, true, 303);
            exit;
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $mail->ErrorInfo . ' Exception: ' . $e->getMessage());
            unset($_SESSION['otp'], $_SESSION['otp_expiry']);
            back_with_error('Could not send OTP email. Please try again.');
        }
    } else {
        back_with_error('Invalid request method.', 303);
    }
} catch (Throwable $e) {
    // Generic server error
    back_with_error('Server error. Please try again.');
}
