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

// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php'; // must define $pdo (PDO)

// ---- Load PHPMailer (prefer Composer, fallback to manual includes) ----
if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    $vendorAutoload = $root_path . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        require_once $root_path . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once $root_path . '/vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once $root_path . '/vendor/phpmailer/phpmailer/src/Exception.php';
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
        
        // Additional information fields
        $isPWD = isset($_POST['isPWD']) ? 1 : 0;
        $pwd_id_number = isset($_POST['pwd_id_number']) ? trim((string)$_POST['pwd_id_number']) : '';
        $isPhilHealth = isset($_POST['isPhilHealth']) ? 1 : 0;
        $philhealth_type = isset($_POST['philhealth_type']) ? trim((string)$_POST['philhealth_type']) : '';
        $philhealth_id_number = isset($_POST['philhealth_id_number']) ? trim((string)$_POST['philhealth_id_number']) : '';
        $isSenior = isset($_POST['isSenior']) ? 1 : 0;
        $senior_citizen_id = isset($_POST['senior_citizen_id']) ? trim((string)$_POST['senior_citizen_id']) : '';
        
        // Emergency contact fields (for minors)
        $emergency_first_name = isset($_POST['emergency_first_name']) ? trim((string)$_POST['emergency_first_name']) : '';
        $emergency_last_name = isset($_POST['emergency_last_name']) ? trim((string)$_POST['emergency_last_name']) : '';
        $emergency_relationship = isset($_POST['emergency_relationship']) ? trim((string)$_POST['emergency_relationship']) : '';
        $emergency_contact_number = isset($_POST['emergency_contact_number']) ? trim((string)$_POST['emergency_contact_number']) : '';
        
        $email = strtolower($email);

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
            'isPWD'       => $isPWD,
            'pwd_id_number' => $pwd_id_number,
            'isPhilHealth' => $isPhilHealth,
            'philhealth_type' => $philhealth_type,
            'philhealth_id_number' => $philhealth_id_number,
            'isSenior'    => $isSenior,
            'senior_citizen_id' => $senior_citizen_id,
            'emergency_first_name' => $emergency_first_name,
            'emergency_last_name' => $emergency_last_name,
            'emergency_relationship' => $emergency_relationship,
            'emergency_contact_number' => $emergency_contact_number
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

        // --- Barangay validation - load from database ---
        $barangay_valid = false;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM barangay WHERE barangay_name = ? AND status = ?');
            if ($stmt && $stmt->execute([$barangay, 'active'])) {
                $result = $stmt->fetchColumn();
                if ($result && (int)$result > 0) {
                    $barangay_valid = true;
                }
            }
        } catch (Throwable $e) {
            error_log('Barangay validation error: ' . $e->getMessage());
        }
        if (!$barangay_valid) {
            back_with_error('Please select a valid barangay.');
        }

        // --- DOB validation (strict YYYY-MM-DD) ---
        $dob = isset($_POST['dob']) ? trim((string)$_POST['dob']) : '';

        // Use '!' to reset fields and avoid weird carry-overs
        $dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);

        // Strict: must parse AND round-trip to exactly the same string
        if (!$dobDate || $dobDate->format('Y-m-d') !== $dob) {
            back_with_error('Date of birth must be in YYYY-MM-DD format.');
        }

        // Disallow future dates and absurdly old ages
        $today = new DateTimeImmutable('today');
        if ($dobDate > $today) {
            back_with_error('Date of birth cannot be in the future.');
        }
        $oldest = $today->modify('-120 years');
        if ($dobDate < $oldest) {
            back_with_error('Please enter a valid date of birth.');
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

        // --- Password policy (len + upper + lower + digit) ---
        if (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/\d/', $password)
        ) {
            back_with_error('Password must be at least 8 characters with uppercase, lowercase, and a number.');
        }

        // --- Additional information validation ---
        // PWD validation
        if ($isPWD && empty($pwd_id_number)) {
            back_with_error('PWD ID Number is required when PWD is selected.');
        }

        // PhilHealth validation
        if ($isPhilHealth) {
            if (empty($philhealth_type) || !in_array($philhealth_type, ['Member', 'Beneficiary'], true)) {
                back_with_error('Valid PhilHealth membership type is required.');
            }
            if (empty($philhealth_id_number)) {
                back_with_error('PhilHealth ID Number is required when PhilHealth is selected.');
            }
            // Validate PhilHealth ID format (12 digits)
            $philhealth_digits = preg_replace('/\D/', '', $philhealth_id_number);
            if (strlen($philhealth_digits) !== 12) {
                back_with_error('PhilHealth ID must be 12 digits.');
            }
            $philhealth_id_number = $philhealth_digits; // Store normalized
        }

        // Senior Citizen validation
        if ($isSenior) {
            if (empty($senior_citizen_id)) {
                back_with_error('Senior Citizen ID is required when Senior Citizen is selected.');
            }
            
            // Validate age for senior citizen (60+)
            $dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
            if ($dobDate) {
                $today = new DateTimeImmutable('today');
                $age = $today->diff($dobDate)->y;
                if ($age < 60) {
                    back_with_error('You must be 60 years or older to register as a Senior Citizen.');
                }
            }
        }

        // Emergency contact validation for minors
        $dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
        if ($dobDate) {
            $today = new DateTimeImmutable('today');
            $age = $today->diff($dobDate)->y;
            
            if ($age < 18) {
                if (empty($emergency_first_name)) {
                    back_with_error('Guardian first name is required for patients under 18.');
                }
                if (empty($emergency_last_name)) {
                    back_with_error('Guardian last name is required for patients under 18.');
                }
                if (empty($emergency_relationship)) {
                    back_with_error('Guardian relationship is required for patients under 18.');
                }
                if (empty($emergency_contact_number)) {
                    back_with_error('Guardian contact number is required for patients under 18.');
                }
                
                // Validate emergency contact number format
                $emergency_digits = preg_replace('/\D+/', '', $emergency_contact_number);
                if (!preg_match('/^9\d{9}$/', $emergency_digits)) {
                    back_with_error('Guardian contact number must be a valid PH mobile number.');
                }
                $emergency_contact_number = $emergency_digits; // Store normalized
            }
        }


        // --- Get barangay_id from barangay name ---
        $barangay_id = null;
        try {
            $stmt = $pdo->prepare('SELECT barangay_id FROM barangay WHERE barangay_name = ?');
            if ($stmt && $stmt->execute([$barangay])) {
                $result = $stmt->fetchColumn();
                if ($result !== false) {
                    $barangay_id = (int)$result;
                }
            }
        } catch (Throwable $e) {
            error_log('Barangay lookup error: ' . $e->getMessage());
        }
        if (!$barangay_id) {
            back_with_error('Invalid barangay selected.');
        }

        // --- Duplicate check ---
        $count = 0;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE first_name = ? AND last_name = ? AND date_of_birth = ? AND barangay_id = ?');
            if ($stmt && $stmt->execute([$first_name, $last_name, $dob, $barangay_id])) {
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

        // Calculate age for session storage
        $dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
        $today = new DateTimeImmutable('today');
        $age = $dobDate ? $today->diff($dobDate)->y : 999; // Default to adult if DOB parsing fails

        // --- Store registration data & OTP in session (NO plaintext password) ---
        $_SESSION['registration'] = [
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'middle_name'  => isset($_POST['middle_name']) ? trim((string)$_POST['middle_name']) : '',
            'suffix'       => isset($_POST['suffix']) ? trim((string)$_POST['suffix']) : '',
            'barangay'     => $barangay,
            'barangay_id'  => $barangay_id,
            'dob'          => isset($_POST['dob']) ? trim((string)$_POST['dob']) : '',
            'sex'          => $sex,
            'contact_num'  => $normalizedContactNum, // <- use normalized digits
            'email'        => $email,                // already lowercased
            'password'     => $hashed,               // store hashed only
            'isPWD'        => $isPWD,
            'pwd_id_number' => $pwd_id_number,
            'isPhilHealth' => $isPhilHealth,
            'philhealth_type' => $philhealth_type,
            'philhealth_id_number' => $philhealth_id_number,
            'isSenior'     => $isSenior,
            'senior_citizen_id' => $senior_citizen_id,
            // Only store emergency contact data if patient is a minor
            'emergency_first_name' => $age < 18 ? $emergency_first_name : '',
            'emergency_last_name' => $age < 18 ? $emergency_last_name : '',
            'emergency_relationship' => $age < 18 ? $emergency_relationship : '',
            'emergency_contact_number' => $age < 18 ? $emergency_contact_number : ''
        ];
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = $otp_expiry;

        // --- Send OTP via PHPMailer ---
        // For development: bypass email if SMTP_PASS is empty or 'disabled'
        $bypassEmail = empty($_ENV['SMTP_PASS']) || $_ENV['SMTP_PASS'] === 'disabled';
        
        if ($bypassEmail) {
            // Development mode: show OTP directly and redirect immediately
            error_log("DEVELOPMENT MODE: OTP for {$email} is: {$otp}");
            $_SESSION['dev_message'] = "DEVELOPMENT MODE: Your OTP is {$otp}";
            header('Location: ' . $otp_page, true, 303);
            exit;
        }
        
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
            
            // Add debugging for development
            if (($debug ?? false)) {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = 'error_log';
            }
            
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
            // Log detailed error information
            $errorDetails = 'PHPMailer error: ' . $mail->ErrorInfo . ' Exception: ' . $e->getMessage();
            error_log($errorDetails);
            
            // For development, also log to mail_error.log
            $logEntry = date('Y-m-d H:i:s') . ' | ' . $errorDetails . PHP_EOL;
            file_put_contents(__DIR__ . '/tools/mail_error.log', $logEntry, FILE_APPEND | LOCK_EX);
            
            unset($_SESSION['otp'], $_SESSION['otp_expiry']);
            
            // More specific error message
            if (strpos($e->getMessage(), 'authenticate') !== false) {
                back_with_error('Email service is currently unavailable. Please contact the administrator or try again later.');
            } else {
                back_with_error('Could not send OTP email. Please check your email address and try again.');
            }
        }
    } else {
        back_with_error('Invalid request method.', 303);
    }
} catch (Throwable $e) {
    // Generic server error
    back_with_error('Server error. Please try again.');
}
