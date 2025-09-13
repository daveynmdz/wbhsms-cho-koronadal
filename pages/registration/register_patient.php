<?php
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
// - Redirects to verify_otp.php on success, back to patient_registration.php on error

declare(strict_types=1);
session_start();

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
$otp_page    = '../../pages/registration/verify_otp.php';
$return_page = isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== '' ? $_SERVER['HTTP_REFERER'] : 'patient_registration.php';

// ---- Helper: redirect back with an error message ----
function back_with_error(string $msg, int $http_code = 302): void {
    $_SESSION['registration_error'] = $msg;
    http_response_code($http_code);
    global $return_page;
    header('Location: ' . $return_page);
    exit;
}

// ---- Make PDO throw exceptions (optional but helpful) ----
if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        back_with_error('Invalid request method.', 303);
    }

    // --- Collect fields ---
    $first_name  = isset($_POST['first_name']) ? trim((string)$_POST['first_name']) : '';
    $last_name   = isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '';
    $dob         = isset($_POST['dob']) ? trim((string)$_POST['dob']) : '';
    $email       = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $contact_num = isset($_POST['contact_num']) ? trim((string)$_POST['contact_num']) : '';
    $barangay    = isset($_POST['barangay']) ? trim((string)$_POST['barangay']) : '';
    $password    = isset($_POST['password']) ? (string)$_POST['password'] : '';

    // --- Required fields ---
    if ($first_name === '' || $last_name === '' || $dob === '' || $email === '' || $contact_num === '' || $barangay === '' || $password === '') {
        back_with_error('All fields are required.');
    }

    // --- Email ---
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        back_with_error('Please enter a valid email address.');
    }

    // --- Phone normalize length check ---
    $normalizedPhone = preg_replace('/[^0-9+]/', '', $contact_num);
    if (strlen($normalizedPhone) < 7 || strlen($normalizedPhone) > 20) {
        back_with_error('Please enter a valid contact number.');
    }

    // --- DOB format YYYY-MM-DD and not future ---
    $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
    $dobErrors = DateTime::getLastErrors();
    if (!$dobDate || $dobErrors['warning_count'] > 0 || $dobErrors['error_count'] > 0) {
        back_with_error('Date of birth must be in YYYY-MM-DD format.');
    }
    $today = new DateTime('today');
    if ($dobDate > $today) {
        back_with_error('Date of birth cannot be in the future.');
    }

    // --- Password policy ---
    if (strlen($password) < 8) {
        back_with_error('Password must be at least 8 characters long.');
    }

    // --- Duplicate check ---
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE first_name = ? AND last_name = ? AND dob = ? AND barangay = ?');
    $stmt->execute([$first_name, $last_name, $dob, $barangay]);
    if ((int)$stmt->fetchColumn() > 0) {
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
        'dob'          => $dob,
        'sex'          => isset($_POST['sex']) ? trim((string)$_POST['sex']) : '',
        'contact_num'  => $contact_num,
        'email'        => $email,
        'password'     => $hashed // store hashed only
    ];
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = $otp_expiry;

    // --- Send OTP via PHPMailer ---
    $mail = new PHPMailer(true);
    try {
        // SMTP config (TODO: replace with your real SMTP credentials)
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';   // e.g., smtp.gmail.com
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your@email.com';
        $mail->Password   = 'yourpassword';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 'tls'
        $mail->Port       = 587;

        $mail->setFrom('no-reply@yourdomain.tld', 'CHO Koronadal');
        $mail->addAddress($email, $first_name . ' ' . $last_name);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for CHO Koronadal Registration';
        $mail->Body    = '<p>Your One-Time Password (OTP) for registration is:</p><h2 style="letter-spacing:2px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</h2><p>This code will expire in 5 minutes.</p>';
        $mail->AltBody = "Your OTP is: {$otp} (expires in 5 minutes)";

        $mail->send();

        // Success → redirect to OTP page (no artificial delay; your UI shows a spinner)
        header('Location: ' . $otp_page);
        exit;
    } catch (Exception $e) {
        // Cleanup OTP if email failed (optional)
        unset($_SESSION['otp'], $_SESSION['otp_expiry']);
        back_with_error('Could not send OTP email. Please try again.');
    }
} catch (Throwable $e) {
    // Generic server error
    back_with_error('Server error. Please try again.');
}